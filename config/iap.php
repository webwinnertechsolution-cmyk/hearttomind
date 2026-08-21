<?php

/**
 * In-app purchase verification settings.
 *
 * All secrets come from the environment — never commit them. See the handoff
 * notes for how to obtain each value.
 */
return [
    'apple' => [
        // Must match the iOS app bundle identifier.
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.maditam.hearttomind'),
        // App-specific shared secret from App Store Connect (Auto-renewable subscriptions).
        'shared_secret' => env('APPLE_IAP_SHARED_SECRET'),
        // Production first; the verifier auto-retries sandbox on status 21007.
        'verify_url' => env('APPLE_IAP_VERIFY_URL', 'https://buy.itunes.apple.com/verifyReceipt'),
        'sandbox_url' => env('APPLE_IAP_SANDBOX_URL', 'https://sandbox.itunes.apple.com/verifyReceipt'),
    ],

    'google' => [
        // Android applicationId.
        'package_name' => env('GOOGLE_PLAY_PACKAGE', 'com.maditam.hearttomind'),
        // Absolute path to the Play service-account JSON key (androidpublisher scope).
        'service_account_json' => env(
            'GOOGLE_PLAY_SERVICE_ACCOUNT_JSON',
            storage_path('app/google-play-service-account.json')
        ),
    ],

    // Maps each store product id to the canonical plan length (months).
    // Used as a fallback when the store does not report an explicit expiry.
    'products' => [
        'com.hearttomind.premium.1month' => ['months' => 1],
        'com.hearttomind.subscription.half6year' => ['months' => 6],
        'com.hearttomind.premium.annual' => ['months' => 12],
    ],
];
