<?php

namespace App\Services\Purchase;

use Carbon\Carbon;

/**
 * Normalized outcome of a store receipt/token verification, independent of
 * whether it came from Apple or Google.
 */
class PurchaseResult
{
    public function __construct(
        public bool $valid,
        public string $platform,
        public ?string $productId = null,
        public ?string $transactionId = null,
        public ?string $originalTransactionId = null,
        public ?Carbon $expiresAt = null,
        public array $raw = [],
        public ?string $error = null,
    ) {
    }

    public static function invalid(string $platform, string $error, array $raw = []): self
    {
        return new self(valid: false, platform: $platform, raw: $raw, error: $error);
    }

    /** Active only when the verification succeeded and the expiry is in the future. */
    public function isActive(): bool
    {
        return $this->valid
            && $this->expiresAt !== null
            && $this->expiresAt->isFuture();
    }

    /**
     * A transient, retryable failure (store unreachable, server misconfig, store
     * internal error, or a purchase that simply hasn't propagated yet) as opposed
     * to a genuine rejection (refunded, wrong product, expired). The API surfaces
     * these as HTTP 202 so the client keeps polling instead of telling the user
     * the purchase failed.
     */
    public function isTransient(): bool
    {
        if ($this->valid || $this->error === null) {
            return false;
        }

        static $retryable = [
            'apple_unreachable',
            'apple_not_configured',
            'google_unreachable',
            'google_not_configured',
            'google_auth_failed',
        ];
        if (in_array($this->error, $retryable, true)) {
            return true;
        }

        // Apple internal-server statuses: 21005 (receipt server unavailable) and
        // 21100-21199 (internal data access errors) are retryable, not rejections.
        if (preg_match('/^apple_status_(\d+)$/', $this->error, $m)) {
            $code = (int) $m[1];
            return $code === 21005 || ($code >= 21100 && $code <= 21199);
        }

        // Google Play Developer API 429 / 5xx are retryable.
        if (preg_match('/^google_http_(\d+)$/', $this->error, $m)) {
            $code = (int) $m[1];
            return $code === 429 || $code >= 500;
        }

        return false;
    }
}
