<?php

namespace App\Services\Purchase;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Log;

/**
 * Verifies an Apple-signed JWS (App Store Server Notifications V2 and the inner
 * signedTransactionInfo / signedRenewalInfo objects).
 *
 * Each JWS header carries an x5c certificate chain: [leaf, intermediate, root].
 * We:
 *   1. rebuild leaf + intermediate from x5c,
 *   2. verify the intermediate is signed by the PINNED Apple Root CA - G3
 *      (so an attacker can't supply their own root),
 *   3. verify the leaf is signed by the intermediate,
 *   4. verify the JWS signature with the leaf certificate's public key (ES256).
 * Returns the decoded payload array, or null on any failure.
 */
class AppleJwsVerifier
{
    /** Absolute path to the pinned Apple Root CA - G3 PEM. */
    private function rootCaPath(): string
    {
        return storage_path('app/certs/AppleRootCA-G3.pem');
    }

    public function verify(string $jws): ?array
    {
        try {
            $parts = explode('.', $jws);
            if (count($parts) !== 3) {
                Log::warning('Apple JWS: malformed token');
                return null;
            }

            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
            if (!is_array($header) || ($header['alg'] ?? null) !== 'ES256') {
                Log::warning('Apple JWS: unexpected alg/header');
                return null;
            }

            $x5c = $header['x5c'] ?? [];
            if (!is_array($x5c) || count($x5c) < 2) {
                Log::warning('Apple JWS: missing x5c chain');
                return null;
            }

            $leafPem = $this->derToPem($x5c[0]);
            $intermediatePem = $this->derToPem($x5c[1]);

            $rootPem = @file_get_contents($this->rootCaPath());
            if ($rootPem === false || $rootPem === '') {
                Log::error('Apple JWS: pinned Apple Root CA - G3 not found at ' . $this->rootCaPath());
                return null;
            }

            // (2) intermediate must be signed by the PINNED Apple root.
            if (openssl_x509_verify($intermediatePem, $rootPem) !== 1) {
                Log::warning('Apple JWS: intermediate not chained to pinned Apple root');
                return null;
            }
            // (3) leaf must be signed by the intermediate.
            if (openssl_x509_verify($leafPem, $intermediatePem) !== 1) {
                Log::warning('Apple JWS: leaf not chained to intermediate');
                return null;
            }

            // (4) verify the JWS signature with the leaf public key.
            $leafKey = openssl_pkey_get_public($leafPem);
            if ($leafKey === false) {
                Log::warning('Apple JWS: cannot read leaf public key');
                return null;
            }
            $leafPubPem = openssl_pkey_get_details($leafKey)['key'] ?? null;
            if (!$leafPubPem) {
                Log::warning('Apple JWS: cannot export leaf public key');
                return null;
            }

            JWT::$leeway = 60; // tolerate minor clock skew
            $payload = JWT::decode($jws, new Key($leafPubPem, 'ES256'));

            return json_decode(json_encode($payload), true);
        } catch (\Throwable $e) {
            Log::warning('Apple JWS verify failed: ' . $e->getMessage());
            return null;
        }
    }

    /** Wrap a base64 DER certificate (from x5c) into PEM form. */
    private function derToPem(string $der): string
    {
        return "-----BEGIN CERTIFICATE-----\n"
            . chunk_split($der, 64, "\n")
            . "-----END CERTIFICATE-----\n";
    }
}
