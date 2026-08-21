<?php

namespace App\Services\Purchase;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mints an OAuth2 access token for the Google Play Developer API from a
 * service-account JSON key, using the JWT-bearer grant. Implemented with
 * openssl + Guzzle so no extra composer dependency (google/apiclient) is
 * required, keeping the handoff self-contained.
 */
class GoogleAccessToken
{
    private const SCOPE = 'https://www.googleapis.com/auth/androidpublisher';
    private const CACHE_KEY = 'iap.google.access_token';

    /** Returns a cached or freshly-minted access token, or null on failure. */
    public function get(): ?string
    {
        $cached = Cache::get(self::CACHE_KEY);
        if ($cached) {
            return $cached;
        }

        $credentials = $this->loadCredentials();
        if ($credentials === null) {
            return null;
        }

        $jwt = $this->buildSignedJwt($credentials);
        if ($jwt === null) {
            return null;
        }

        try {
            $tokenUri = $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token';
            $res = Http::asForm()->timeout(20)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($res->failed()) {
                Log::error('Google IAP: token exchange HTTP ' . $res->status() . ' - ' . $res->body());
                return null;
            }

            $token = $res->json('access_token');
            $expiresIn = (int) $res->json('expires_in', 3600);
            if ($token) {
                Cache::put(self::CACHE_KEY, $token, max(60, $expiresIn - 60));
            }
            return $token;
        } catch (\Throwable $e) {
            Log::error('Google IAP: token exchange failed - ' . $e->getMessage());
            return null;
        }
    }

    private function loadCredentials(): ?array
    {
        $path = config('iap.google.service_account_json');
        if (!$path || !is_file($path)) {
            Log::error('Google IAP: service account JSON not found at ' . $path);
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || empty($data['private_key']) || empty($data['client_email'])) {
            Log::error('Google IAP: service account JSON is malformed.');
            return null;
        }
        return $data;
    }

    private function buildSignedJwt(array $credentials): ?string
    {
        $now = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => self::SCOPE,
            'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ];

        $segments = [
            $this->base64Url(json_encode($header)),
            $this->base64Url(json_encode($claims)),
        ];
        $signingInput = implode('.', $segments);

        $signature = '';
        $ok = openssl_sign($signingInput, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256);
        if (!$ok) {
            Log::error('Google IAP: failed to sign JWT with service account key.');
            return null;
        }

        $segments[] = $this->base64Url($signature);
        return implode('.', $segments);
    }

    private function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
