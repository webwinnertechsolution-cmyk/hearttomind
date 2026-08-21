<?php

namespace App\Services\Purchase;

use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies an App Store receipt against Apple's verifyReceipt endpoint using the
 * app-specific shared secret. Production is tried first with an automatic
 * sandbox fallback (status 21007), which is the behaviour Apple requires so the
 * same build passes both review (sandbox) and production.
 *
 * NOTE: verifyReceipt is the legacy endpoint. It remains functional for
 * auto-renewable subscriptions and needs only a shared secret (no signed JWT),
 * which keeps this dependency-free. The driver is isolated so it can be swapped
 * for the App Store Server API later without touching callers.
 */
class AppleReceiptVerifier
{
    public function verify(string $receiptData, ?string $expectedProductId = null): PurchaseResult
    {
        // StoreKit 2 (iOS 15+ via in_app_purchase) sends a SIGNED JWS transaction,
        // not the classic base64 app receipt. Apple's legacy verifyReceipt rejects
        // that as malformed (status 21002 — the exact failure seen in production).
        // Detect and verify it as a JWS using Apple's pinned key chain instead.
        if ($this->looksLikeJws($receiptData)) {
            return $this->verifyStoreKit2Jws($receiptData, $expectedProductId);
        }

        $secret = config('iap.apple.shared_secret');
        if (empty($secret)) {
            Log::error('Apple IAP: shared secret not configured (APPLE_IAP_SHARED_SECRET).');
            return PurchaseResult::invalid('apple', 'apple_not_configured');
        }

        $response = $this->call(config('iap.apple.verify_url'), $receiptData, $secret);
        if ($response === null) {
            return PurchaseResult::invalid('apple', 'apple_unreachable');
        }

        // 21007: receipt is from the sandbox — retry against the sandbox endpoint.
        if (($response['status'] ?? null) === 21007) {
            $response = $this->call(config('iap.apple.sandbox_url'), $receiptData, $secret);
            if ($response === null) {
                return PurchaseResult::invalid('apple', 'apple_unreachable');
            }
        }

        $status = $response['status'] ?? -1;
        if ($status !== 0) {
            Log::warning('Apple IAP: verifyReceipt returned status ' . $status);
            return PurchaseResult::invalid('apple', 'apple_status_' . $status, $response);
        }

        // Validate the receipt belongs to this app.
        $bundleId = $response['receipt']['bundle_id'] ?? null;
        if ($bundleId !== null && $bundleId !== config('iap.apple.bundle_id')) {
            Log::warning("Apple IAP: bundle id mismatch ({$bundleId}).");
            return PurchaseResult::invalid('apple', 'apple_bundle_mismatch', $response);
        }

        $entry = $this->selectLatest($response['latest_receipt_info'] ?? [], $expectedProductId);
        if ($entry === null) {
            return PurchaseResult::invalid('apple', 'apple_no_matching_product', $response);
        }

        $expiresMs = $entry['expires_date_ms'] ?? null;
        $expiresAt = $expiresMs ? Carbon::createFromTimestampMs((int) $expiresMs) : null;

        // A refund/cancellation is reflected by a cancellation_date_ms.
        if (!empty($entry['cancellation_date_ms'])) {
            return new PurchaseResult(
                valid: false,
                platform: 'apple',
                productId: $entry['product_id'] ?? null,
                transactionId: $entry['transaction_id'] ?? null,
                originalTransactionId: $entry['original_transaction_id'] ?? null,
                expiresAt: $expiresAt,
                raw: $response,
                error: 'apple_cancelled',
            );
        }

        return new PurchaseResult(
            valid: true,
            platform: 'apple',
            productId: $entry['product_id'] ?? null,
            transactionId: $entry['transaction_id'] ?? null,
            originalTransactionId: $entry['original_transaction_id'] ?? null,
            expiresAt: $expiresAt,
            raw: $response,
        );
    }

    private function call(string $url, string $receiptData, string $secret): ?array
    {
        try {
            $res = Http::timeout(20)->acceptJson()->post($url, [
                'receipt-data' => $receiptData,
                'password' => $secret,
                'exclude-old-transactions' => true,
            ]);
            if ($res->failed()) {
                Log::error('Apple IAP: HTTP ' . $res->status() . ' from ' . $url);
                return null;
            }
            return $res->json();
        } catch (\Throwable $e) {
            Log::error('Apple IAP: request failed - ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Picks the most recent transaction, preferring the expected product id.
     */
    private function selectLatest(array $infos, ?string $expectedProductId): ?array
    {
        if (empty($infos)) {
            return null;
        }

        $candidates = $expectedProductId
            ? array_values(array_filter($infos, fn ($i) => ($i['product_id'] ?? null) === $expectedProductId))
            : $infos;

        if (empty($candidates)) {
            $candidates = $infos; // fall back to any product on the receipt
        }

        usort($candidates, fn ($a, $b) => (int) ($b['expires_date_ms'] ?? 0) <=> (int) ($a['expires_date_ms'] ?? 0));

        return $candidates[0] ?? null;
    }

    /** A StoreKit 2 transaction is a JWS: three non-empty dot-separated segments. */
    private function looksLikeJws(string $data): bool
    {
        $parts = explode('.', trim($data));
        return count($parts) === 3 && $parts[0] !== '' && $parts[1] !== '' && $parts[2] !== '';
    }

    /**
     * Verifies a StoreKit 2 signed transaction (JWS) and maps it to a
     * PurchaseResult. Reuses the same Apple-root-pinned verifier as the server
     * notifications, so no shared secret / verifyReceipt round-trip is needed.
     */
    private function verifyStoreKit2Jws(string $jws, ?string $expectedProductId): PurchaseResult
    {
        $payload = (new AppleJwsVerifier())->verify($jws);
        if ($payload === null) {
            Log::warning('Apple IAP: StoreKit2 JWS signature verification failed');
            return PurchaseResult::invalid('apple', 'apple_sk2_bad_signature');
        }

        $bundleId = $payload['bundleId'] ?? null;
        if ($bundleId !== null && $bundleId !== config('iap.apple.bundle_id')) {
            Log::warning("Apple IAP: SK2 bundle id mismatch ({$bundleId})");
            return PurchaseResult::invalid('apple', 'apple_bundle_mismatch', $payload);
        }

        $productId = $payload['productId'] ?? null;
        $expiresMs = $payload['expiresDate'] ?? null;
        $expiresAt = $expiresMs ? Carbon::createFromTimestampMs((int) $expiresMs) : null;
        $txnId = isset($payload['transactionId']) ? (string) $payload['transactionId'] : null;
        $origTxnId = isset($payload['originalTransactionId']) ? (string) $payload['originalTransactionId'] : null;

        // A refund/revocation is reflected by revocationDate.
        if (!empty($payload['revocationDate'])) {
            return new PurchaseResult(
                valid: false,
                platform: 'apple',
                productId: $productId,
                transactionId: $txnId,
                originalTransactionId: $origTxnId,
                expiresAt: $expiresAt,
                raw: $payload,
                error: 'apple_cancelled',
            );
        }

        Log::info('Apple IAP: StoreKit2 JWS verified product=' . ($productId ?? 'n/a') . ' origTxn=' . ($origTxnId ?? 'n/a'));

        return new PurchaseResult(
            valid: true,
            platform: 'apple',
            productId: $productId,
            transactionId: $txnId,
            originalTransactionId: $origTxnId,
            expiresAt: $expiresAt,
            raw: $payload,
        );
    }
}
