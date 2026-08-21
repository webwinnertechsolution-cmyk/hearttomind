<?php

namespace App\Services\Purchase;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies an Android subscription purchase via the Google Play Developer API
 * (purchases.subscriptions.get) and acknowledges it if required.
 */
class GooglePlayVerifier
{
    public function __construct(private GoogleAccessToken $accessToken)
    {
    }

    public function verify(string $productId, string $purchaseToken): PurchaseResult
    {
        $package = config('iap.google.package_name');
        if (empty($package)) {
            return PurchaseResult::invalid('google', 'google_not_configured');
        }

        $token = $this->accessToken->get();
        if ($token === null) {
            return PurchaseResult::invalid('google', 'google_auth_failed');
        }

        $base = 'https://androidpublisher.googleapis.com/androidpublisher/v3/applications';
        $url = sprintf(
            '%s/%s/purchases/subscriptions/%s/tokens/%s',
            $base,
            rawurlencode($package),
            rawurlencode($productId),
            rawurlencode($purchaseToken),
        );

        try {
            $res = Http::withToken($token)->timeout(20)->acceptJson()->get($url);
        } catch (\Throwable $e) {
            Log::error('Google IAP: subscriptions.get failed - ' . $e->getMessage());
            return PurchaseResult::invalid('google', 'google_unreachable');
        }

        if ($res->failed()) {
            Log::warning('Google IAP: subscriptions.get HTTP ' . $res->status() . ' - ' . $res->body());
            return PurchaseResult::invalid('google', 'google_http_' . $res->status(), (array) $res->json());
        }

        $data = $res->json();

        $expiryMs = $data['expiryTimeMillis'] ?? null;
        $expiresAt = $expiryMs ? Carbon::createFromTimestampMs((int) $expiryMs) : null;

        // paymentState: 0 pending, 1 received, 2 free trial, 3 deferred. A real
        // payment is required (1/2). A refund sets cancelReason = 1 (or removes
        // paymentState entirely).
        $paymentState = $data['paymentState'] ?? null;
        $cancelReason = $data['cancelReason'] ?? null;

        if ($paymentState === null || $cancelReason === 1) {
            return new PurchaseResult(
                valid: false,
                platform: 'google',
                productId: $productId,
                transactionId: $data['orderId'] ?? null,
                originalTransactionId: $purchaseToken,
                expiresAt: $expiresAt,
                raw: $data,
                error: 'google_not_paid_or_refunded',
            );
        }

        // Acknowledge the purchase so Google does not auto-refund it after 3 days.
        if (($data['acknowledgementState'] ?? null) === 0) {
            $this->acknowledge($token, $package, $productId, $purchaseToken);
        }

        return new PurchaseResult(
            valid: true,
            platform: 'google',
            productId: $productId,
            transactionId: $data['orderId'] ?? null,
            // Google has no separate "original" id; the purchase token is the
            // stable identity across renewals on the same subscription.
            originalTransactionId: $purchaseToken,
            expiresAt: $expiresAt,
            raw: $data,
        );
    }

    private function acknowledge(string $token, string $package, string $productId, string $purchaseToken): void
    {
        $url = sprintf(
            'https://androidpublisher.googleapis.com/androidpublisher/v3/applications/%s/purchases/subscriptions/%s/tokens/%s:acknowledge',
            rawurlencode($package),
            rawurlencode($productId),
            rawurlencode($purchaseToken),
        );
        try {
            Http::withToken($token)->timeout(20)->post($url, []);
        } catch (\Throwable $e) {
            Log::warning('Google IAP: acknowledge failed - ' . $e->getMessage());
        }
    }
}
