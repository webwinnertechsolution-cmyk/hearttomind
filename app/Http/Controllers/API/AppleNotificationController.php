<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Services\Purchase\AppleJwsVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Receives Apple App Store Server Notifications V2 so subscription state stays
 * correct server-side (renewals extend expiry, refunds/expirations revoke
 * access) WITHOUT waiting for the client to re-verify.
 *
 * Apple POSTs a single JSON body: { "signedPayload": "<JWS>" }. We verify the
 * signature against the pinned Apple Root CA, decode the inner transaction +
 * renewal info, and update the matching subscription. Always 200 once the
 * signature is valid (even if we don't track the txn) so Apple stops retrying;
 * 400 only when the signature itself is invalid.
 */
class AppleNotificationController extends Controller
{
    public function __construct(private AppleJwsVerifier $jws)
    {
    }

    public function handle(Request $request)
    {
        $signedPayload = $request->input('signedPayload');
        if (!is_string($signedPayload) || $signedPayload === '') {
            return response()->json(['ok' => false, 'reason' => 'missing_payload'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->jws->verify($signedPayload);
        if ($payload === null) {
            Log::warning('Apple S2S: signature verification failed');
            return response()->json(['ok' => false, 'reason' => 'bad_signature'], Response::HTTP_BAD_REQUEST);
        }

        $type = $payload['notificationType'] ?? '';
        $subtype = $payload['subtype'] ?? '';
        $data = $payload['data'] ?? [];

        // Must be for THIS app.
        $bundleId = $data['bundleId'] ?? null;
        if ($bundleId !== null && $bundleId !== config('iap.apple.bundle_id')) {
            Log::warning("Apple S2S: bundle id mismatch ({$bundleId})");
            return response()->json(['ok' => true]); // acknowledge + ignore
        }

        // Decode the inner signed transaction + renewal info (same JWS scheme).
        $txn = isset($data['signedTransactionInfo'])
            ? $this->jws->verify($data['signedTransactionInfo'])
            : null;
        $renewal = isset($data['signedRenewalInfo'])
            ? $this->jws->verify($data['signedRenewalInfo'])
            : null;

        $originalTxnId = $txn['originalTransactionId'] ?? null;
        $productId = $txn['productId'] ?? null;
        $expiresMs = $txn['expiresDate'] ?? null;
        $expiresAt = $expiresMs ? Carbon::createFromTimestampMs((int) $expiresMs) : null;
        $autoRenew = $renewal['autoRenewStatus'] ?? null; // 1 = on, 0 = off

        Log::info("Apple S2S: {$type}/{$subtype} origTxn={$originalTxnId} product={$productId} expires=" . ($expiresAt?->toIso8601String() ?? 'n/a'));

        if (!$originalTxnId) {
            return response()->json(['ok' => true]);
        }

        // Only UPDATE a subscription we already track (created by a server-verified
        // purchase, which attaches the user). A notification has no user to bind a
        // brand-new entitlement to — the client's verify call creates that row.
        $sub = Subscription::where('platform', 'apple')
            ->where('original_transaction_id', $originalTxnId)
            ->orderByDesc('id')
            ->first();

        if (!$sub) {
            Log::info("Apple S2S: no local subscription for origTxn={$originalTxnId} ({$type}) — client verify will create it");
            return response()->json(['ok' => true]);
        }

        switch ($type) {
            case 'SUBSCRIBED':
            case 'DID_RENEW':
            case 'OFFER_REDEEMED':
            case 'DID_CHANGE_RENEWAL_PREF':
                if ($expiresAt) {
                    $sub->expired_at = $expiresAt;
                }
                $sub->status = 'active';
                $sub->is_paid = true;
                break;

            case 'DID_CHANGE_RENEWAL_STATUS':
                // Auto-renew toggled on/off — access continues until expiry.
                if ($expiresAt) {
                    $sub->expired_at = $expiresAt;
                }
                $sub->status = 'active';
                break;

            case 'EXPIRED':
            case 'GRACE_PERIOD_EXPIRED':
                $sub->status = 'expired';
                break;

            case 'REFUND':
            case 'REVOKE':
                $sub->status = 'refunded';
                break;

            case 'DID_FAIL_TO_RENEW':
                // Billing retry / grace period — keep current expiry, don't revoke.
                $sub->status = 'billing_retry';
                break;

            default:
                Log::info("Apple S2S: unhandled notificationType {$type} — acknowledged, no change");
                return response()->json(['ok' => true]);
        }

        $sub->save();
        Log::info("Apple S2S: subscription {$sub->id} → status={$sub->status} expires=" . optional($sub->expired_at)->toIso8601String() . " autoRenew=" . ($autoRenew ?? 'n/a'));

        return response()->json(['ok' => true]);
    }
}
