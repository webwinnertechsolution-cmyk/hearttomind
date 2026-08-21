<?php

use App\Http\Controllers\API\AlbamController;
use App\Http\Controllers\API\AuthController;
use App\Http\Controllers\API\BannerController;
use App\Http\Controllers\API\CategoryController;
use App\Http\Controllers\API\ConfigController;
use App\Http\Controllers\API\ContactController;
use App\Http\Controllers\API\FavoriteConstroller;
use App\Http\Controllers\API\AppleNotificationController;
use App\Http\Controllers\API\NotificationsController;
use App\Http\Controllers\API\MediaStreamController;
use App\Http\Controllers\API\PlayListController;
use App\Http\Controllers\API\PurchaseController;
use App\Http\Controllers\API\SettingController;
use App\Http\Controllers\API\ShiftController;
use App\Http\Controllers\API\SubscriptionController;
use App\Http\Controllers\API\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('/auth/sign-in', [AuthController::class, 'signIn']);
    Route::post('/auth/sign-up', [AuthController::class, 'signUp']);
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/auth/otp-verify', [AuthController::class, 'otpVerify']);
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/auth/social-login', [AuthController::class, 'socialLogin']);
    Route::post('/auth/email-verify', [AuthController::class, 'emailVerify']);

    Route::get('/config', [ConfigController::class, 'index']);
    Route::get('/banners', [BannerController::class, 'index']);
    Route::get('/shifts', [ShiftController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/categories/{id}', [CategoryController::class, 'show']);
    Route::get('/albams', [AlbamController::class, 'index']);
    Route::get('/playlists', [PlayListController::class, 'index']);
    Route::post('/playlists/{id}/view-count', [PlayListController::class, 'viewCount']);
    Route::get('/playlists/{playlist}/readmore', [PlayListController::class, 'readmore']);
    Route::post('/contact', [ContactController::class, 'store']);
    Route::get('/legal-pages/{page}', [SettingController::class, 'show']);
    Route::get('/general-settings', [SettingController::class, 'generalSettings']);
    Route::get('/switch-language/{local}', [SettingController::class, 'switchLang']);
    Route::get('/subscription-plans', [SubscriptionController::class, 'index']);
    // Apple App Store Server Notifications V2 (renewals/cancels/refunds). Public:
    // Apple calls it unauthenticated; the request is trusted via JWS signature.
    Route::post('/purchase/apple/notifications', [AppleNotificationController::class, 'handle']);
    Route::get('/subscriptions/payment-view/{transaction_id}', [SubscriptionController::class, 'paymentView']);

    // Premium media delivery: short-lived signed URL, no auth header needed
    // (the signature is the credential). Issued only to entitled users by the
    // play-url endpoint below.
    Route::get('/media/{playlist}/file', [MediaStreamController::class, 'stream'])
        ->middleware('signed')
        ->name('media.stream');

    Route::middleware('auth:api')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/token-resend', [AuthController::class, 'tokenResend']);

        Route::get('/user', [UserController::class, 'index']);
        Route::post('/user/profile', [UserController::class, 'updateProfile']);
        Route::post('/user/password', [UserController::class, 'passwordUpdate']);
        Route::delete('/user', [UserController::class, 'deleteProfile']);
        // Per-user listening stats (persist across app reinstall).
        Route::get('/user/stats', [UserController::class, 'stats']);
        Route::post('/user/stats', [UserController::class, 'incrementStats']);

        Route::get('/favorites', [FavoriteConstroller::class, 'index']);
        Route::post('/favorites', [FavoriteConstroller::class, 'store']);

        Route::get('/notifications', [NotificationsController::class, 'index']);
        Route::post('/notifications', [NotificationsController::class, 'store']);
        Route::post('/notifications/{notification}', [NotificationsController::class, 'update']);
        Route::delete('/notifications/{notification}', [NotificationsController::class, 'delete']);

        Route::post('/subscriptions/store', [SubscriptionController::class, 'store']);
        Route::post('/subscriptions/buy', [SubscriptionController::class, 'buySubscription']);
        Route::get('/subscriptions/my-plans', [SubscriptionController::class, 'myPlans']);

        // Server-verified IAP entitlement (Apple receipt / Google purchase token).
        Route::post('/purchase/verify', [PurchaseController::class, 'verify']);

        // Returns a short-lived signed streaming URL for a playlist's media,
        // after checking the caller is entitled to it.
        Route::get('/playlists/{playlist}/play-url', [MediaStreamController::class, 'playUrl']);
    });
});
