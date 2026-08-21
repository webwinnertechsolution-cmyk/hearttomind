<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SubscriptionResouorce;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Purchase\PurchaseVerificationService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Server-side verification of Apple / Google in-app purchases. This is the ONLY
 * path through which a mobile purchase may grant entitlement — the client can
 * no longer self-report a subscription.
 */
class PurchaseController extends Controller
{
    public function __construct(private PurchaseVerificationService $verifier)
    {
    }

    public function verify(Request $request)
    {
        $data = $request->validate([
            'platform' => 'required|in:apple,google',
            'product_id' => 'required|string',
            // Optional: a restored/re-delivered store transaction has no plan id,
            // so we resolve it from product_id below when it's missing.
            'plan_id' => 'nullable|exists:subscription_plans,id',
            // iOS: base64 App Store receipt. Android: Play purchase token.
            'receipt_data' => 'required_if:platform,apple|string',
            'purchase_token' => 'required_if:platform,google|string',
            'transaction_id' => 'nullable|string',
        ]);

        $user = auth()->user();

        $result = $this->verifier->verify($data['platform'], $data['product_id'], [
            'receipt_data' => $data['receipt_data'] ?? null,
            'purchase_token' => $data['purchase_token'] ?? null,
        ]);

        // Gate on a GENUINE receipt (valid = verified by Apple/Google and not
        // refunded/cancelled). We intentionally do NOT use isActive() here: Apple
        // SANDBOX auto-renewables expire in minutes, so a freshly-bought receipt
        // can already report a lapsed expiry by the time we verify — that caused
        // valid purchases to be wrongly rejected (the "error after purchase" the
        // reviewer saw). Expiry is resolved sensibly below.
        if (!$result->valid) {
            // Transient (store unreachable / internal error / not yet propagated)
            // → 202 Accepted so the client keeps polling instead of showing a hard
            // error and leaving the user thinking the purchase failed.
            if ($result->isTransient()) {
                Log::info("Purchase verify pending for user {$user->id}: {$result->error}");
                return $this->json(
                    'Purchase is still being verified.',
                    ['status' => 'pending', 'reason' => $result->error],
                    Response::HTTP_ACCEPTED
                );
            }
            Log::warning("Purchase verify rejected for user {$user->id}: {$result->error}");
            return $this->json(
                'Purchase could not be verified.',
                ['status' => 'failed', 'reason' => $result->error],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Resolve the plan. The client sends plan_id when it knows it, but a
        // restored/re-delivered StoreKit transaction often has none — so fall
        // back to mapping the store product id to a plan by its duration. This
        // removes the client's fragile dependency on a backend plan id (the
        // "Could not match purchase to a plan" dead-end).
        $plan = !empty($data['plan_id'])
            ? SubscriptionPlan::find($data['plan_id'])
            : $this->planForProduct($result->productId ?? $data['product_id']);

        if (!$plan) {
            Log::warning("Purchase verify: no plan for product {$data['product_id']} (user {$user->id}).");
            return $this->json(
                'Purchase could not be verified.',
                ['status' => 'failed', 'reason' => 'plan_not_found'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        $planMonths = (int) ($plan->duration ?? 1);
        if ($planMonths < 1) {
            $planMonths = 1;
        }

        $receiptExpiry = $result->expiresAt;
        if ($receiptExpiry !== null && $receiptExpiry->isFuture()) {
            // Normal active subscription — trust the store's expiry.
            $expiresAt = $receiptExpiry;
        } elseif ($receiptExpiry === null) {
            // No expiry reported (e.g. non-subscription) — grant the plan length.
            $expiresAt = now()->addMonths($planMonths);
        } elseif ($receiptExpiry->greaterThan(now()->subDay())) {
            // Lapsed within the last 24h → sandbox short-expiry race on a fresh
            // purchase. Grant a full plan period from now.
            $expiresAt = now()->addMonths($planMonths);
        } else {
            // Genuinely expired (lapsed more than a day ago) → do not grant.
            Log::warning("Purchase verify: expired receipt for user {$user->id}.");
            return $this->json(
                'Purchase could not be verified.',
                ['reason' => 'expired'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        // Idempotent on the store's stable transaction identity: a re-verify or
        // a restore updates the existing row instead of stacking subscriptions.
        $subscription = Subscription::updateOrCreate(
            [
                'platform' => $result->platform,
                'original_transaction_id' => $result->originalTransactionId,
            ],
            [
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'product_id' => $result->productId ?? $data['product_id'],
                'provider_transaction_id' => $result->transactionId,
                'purchase_token' => $data['purchase_token'] ?? null,
                'verification_payload' => $result->raw,
                'status' => 'active',
                'is_paid' => true,
                'amount' => $plan->amount,
                'expired_at' => $expiresAt,
            ]
        );

        return $this->json('Purchase verified.', [
            'status' => 'verified',
            'has_subscribed' => true,
            'subscription_expires_at' => Carbon::parse($subscription->expired_at)->toIso8601String(),
            'subscription' => SubscriptionResouorce::make($subscription),
        ]);
    }

    /**
     * Maps a store product id to a SubscriptionPlan by the duration encoded in
     * the id (…1month → 1, …half6year → 6, …annual/…year → 12), matched against
     * the plan's `duration` column (number of months). Lets a purchase verify
     * even when the client cannot supply a plan_id (e.g. a restored transaction).
     */
    private function planForProduct(?string $productId): ?SubscriptionPlan
    {
        if (empty($productId)) {
            return null;
        }

        $id = strtolower($productId);
        $months = null;
        if (str_contains($id, '1month')) {
            $months = 1;
        } elseif (str_contains($id, 'half6year') || str_contains($id, '6month')) {
            $months = 6;
        } elseif (str_contains($id, 'annual') || str_contains($id, '12month') || str_contains($id, 'year')) {
            $months = 12;
        }

        if ($months === null) {
            return null;
        }

        return SubscriptionPlan::where('duration', $months)->first();
    }
}
