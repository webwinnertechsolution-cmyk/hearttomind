<?php

namespace App\Services\Purchase;

use Illuminate\Support\Facades\Log;

/**
 * Single entry point for verifying a store purchase. Dispatches to the correct
 * platform driver and returns a normalized {@see PurchaseResult}.
 */
class PurchaseVerificationService
{
    public function __construct(
        private AppleReceiptVerifier $apple,
        private GooglePlayVerifier $google,
    ) {
    }

    /**
     * @param  array  $payload  ['receipt_data' => ...] for apple, ['purchase_token' => ...] for google
     */
    public function verify(string $platform, string $productId, array $payload): PurchaseResult
    {
        switch ($platform) {
            case 'apple':
                $receipt = $payload['receipt_data'] ?? null;
                if (empty($receipt)) {
                    return PurchaseResult::invalid('apple', 'missing_receipt_data');
                }
                return $this->apple->verify($receipt, $productId);

            case 'google':
                $purchaseToken = $payload['purchase_token'] ?? null;
                if (empty($purchaseToken)) {
                    return PurchaseResult::invalid('google', 'missing_purchase_token');
                }
                return $this->google->verify($productId, $purchaseToken);

            default:
                Log::warning("Purchase verify: unsupported platform '{$platform}'.");
                return PurchaseResult::invalid($platform, 'unsupported_platform');
        }
    }
}
