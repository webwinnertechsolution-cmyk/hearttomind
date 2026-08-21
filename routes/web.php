<?php

use App\Http\Controllers\CreateSuperAdmin;
use App\Http\Controllers\FCMController;
use App\Http\Controllers\MailConfigurationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\SMSConfigurationController;
use App\Http\Controllers\Web\AlbamController;
use App\Http\Controllers\Web\BannerController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\EmailVerifyController;
use App\Http\Controllers\Web\LoginController;
use App\Http\Controllers\Web\NotificationController;
use App\Http\Controllers\Web\PaymentConfigurationController;
use App\Http\Controllers\Web\PlaylistController;
use App\Http\Controllers\Web\ProfileController;
use App\Http\Controllers\Web\SettingController;
use App\Http\Controllers\Web\ShiftController;
use App\Http\Controllers\Web\SubscriptionPlanController;
use App\Http\Controllers\Web\UserController;
use App\Http\Controllers\Web\WebSettingController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/login', [LoginController::class, 'index'])->middleware('guest')->name('login');
    Route::post('/login', [LoginController::class, 'login'])->middleware('guest')->name('login.attempt');
    Route::post('/logout', [LoginController::class, 'logout'])->middleware('auth')->name('logout');

    Route::get('/create-root', [CreateSuperAdmin::class, 'index'])->name('create.root');
    Route::post('/create-root', [CreateSuperAdmin::class, 'store'])->name('create.superadmin');

    Route::get('/email-verify/{userId}/{token}', [EmailVerifyController::class, 'verify'])->name('email-verify');

    Route::get('/', [DashboardController::class, 'index'])
        ->middleware(['auth', 'check_has_root'])
        ->name('root');

    Route::middleware(['auth', 'check_has_root'])->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('user.index');
        Route::get('/users/export', [UserController::class, 'export'])->name('user.export');
        Route::get('/users/{user}/toggle', [UserController::class, 'toggle'])->name('user.status.toggle');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('user.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('user.edit');
        Route::post('/users/{user}/edit', [UserController::class, 'update'])->name('user.update');
        Route::get('/users/{user}/delete', [UserController::class, 'delete'])->name('user.delete');

        Route::get('/categories', [CategoryController::class, 'index'])->name('category.index');
        Route::get('/categories/create', [CategoryController::class, 'create'])->name('category.create');
        Route::post('/categories/create', [CategoryController::class, 'store'])->name('category.store');
        Route::get('/categories/{category}/edit', [CategoryController::class, 'edit'])->name('category.edit');
        Route::post('/categories/{category}/edit', [CategoryController::class, 'update'])->name('category.update');
        Route::get('/categories/{category}/delete', [CategoryController::class, 'delete'])->name('category.delete');
        Route::get('/categories/{category}/toggle', [CategoryController::class, 'toggle'])->name('category.status.toggle');
        Route::get('/categories/{id}/order/{direction}', [CategoryController::class, 'reorder'])->name('category.reorder');
        Route::get('/categories/{category}/tree', [CategoryController::class, 'getAlbams'])->name('category.tree');
        Route::post('/categories/{category}/tree', [CategoryController::class, 'updateAlbum'])->name('category.tree.update');

        Route::get('/albams', [AlbamController::class, 'index'])->name('albam.index');
        Route::get('/albams/create', [AlbamController::class, 'create'])->name('albam.create');
        Route::post('/albams/create', [AlbamController::class, 'store'])->name('albam.store');
        Route::get('/albams/{albam}/edit', [AlbamController::class, 'edit'])->name('albam.edit');
        Route::post('/albams/{albam}/edit', [AlbamController::class, 'update'])->name('albam.update');
        Route::get('/albams/{albam}/delete', [AlbamController::class, 'delete'])->name('albam.delete');
        Route::get('/albams/{albam}/toggle', [AlbamController::class, 'toggle'])->name('albam.status.toggle');
        Route::get('/albams/{albam}/paid-toggle', [AlbamController::class, 'updatePaidStatus'])->name('albam.paid.toggle');
        Route::get('/albams/{id}/order/{direction}', [AlbamController::class, 'reorder'])->name('albam.reorder');
        Route::get('/albams/{albam}/tree', [AlbamController::class, 'getPlaylist'])->name('albam.tree');
        Route::post('/albams/{albam}/tree', [AlbamController::class, 'updatePlaylist'])->name('albam.tree.update');

        Route::get('/playlists', [PlaylistController::class, 'index'])->name('playlist.index');
        Route::get('/playlists/create', [PlaylistController::class, 'create'])->name('playlist.create');
        Route::post('/playlists/create', [PlaylistController::class, 'store'])->name('playlist.store');
        Route::get('/playlists/{playlist}', [PlaylistController::class, 'show'])->name('playlist.show');
        Route::get('/playlists/{playlist}/edit', [PlaylistController::class, 'edit'])->name('playlist.edit');
        Route::post('/playlists/{playlist}/edit', [PlaylistController::class, 'update'])->name('playlist.update');
        Route::get('/playlists/{playlist}/delete', [PlaylistController::class, 'delete'])->name('playlist.delete');
        Route::get('/playlists/{playlist}/toggle', [PlaylistController::class, 'toggle'])->name('playlist.status.toggle');
        Route::get('/playlists/{playlist}/paid-toggle', [PlaylistController::class, 'updatePaidStatus'])->name('playlist.paid.toggle');
        Route::get('/playlists/{id}/order/{direction}', [PlaylistController::class, 'reorder'])->name('playlist.reorder');
        Route::get('/playlists/{playlist}/readmore', [PlaylistController::class, 'readmore'])->name('playlist.readmore');
        Route::post('/playlists/{playlist}/readmore', [PlaylistController::class, 'readmoreUpdate'])->name('playlist.readmore.update');

        Route::get('/shifts', [ShiftController::class, 'index'])->name('shift.index');
        Route::get('/shifts/{shift}/toggle', [ShiftController::class, 'toggle'])->name('shift.status.toggle');
        Route::get('/shifts/{shift}/delete', [ShiftController::class, 'delete'])->name('shift.delete');
        Route::get('/shifts/{shift}/tree', [ShiftController::class, 'getAlbams'])->name('shift.tree');
        Route::post('/shifts/{shift}/tree', [ShiftController::class, 'updateAlbum'])->name('shift.tree.update');

        Route::get('/banners', [BannerController::class, 'index'])->name('banner.index');
        Route::get('/banners/create', [BannerController::class, 'create'])->name('banner.create');
        Route::post('/banners/create', [BannerController::class, 'store'])->name('banner.store');
        Route::get('/banners/{banner}/edit', [BannerController::class, 'edit'])->name('banner.edit');
        Route::post('/banners/{banner}/edit', [BannerController::class, 'update'])->name('banner.update');
        Route::get('/banners/{banner}/delete', [BannerController::class, 'delete'])->name('banner.delete');
        Route::get('/banners/{banner}/toggle', [BannerController::class, 'toggle'])->name('banner.status.toggle');

        Route::get('/notifications', [NotificationController::class, 'index'])->name('notification.index');
        Route::post('/notifications/send', [NotificationController::class, 'sendNotification'])->name('notification.send');
        Route::get('/notifications/filter', [NotificationController::class, 'userFilters'])->name('notification.filter');

        Route::get('/subscription-plans', [SubscriptionPlanController::class, 'index'])->name('subscriptionPlan.index');
        Route::get('/subscription-plans/create', [SubscriptionPlanController::class, 'create'])->name('subscriptionPlan.create');
        Route::post('/subscription-plans/create', [SubscriptionPlanController::class, 'store'])->name('subscriptionPlan.store');
        Route::get('/subscription-plans/{subscriptionPlan}', [SubscriptionPlanController::class, 'show'])->name('subscriptionPlan.show');
        Route::get('/subscription-plans/{subscriptionPlan}/edit', [SubscriptionPlanController::class, 'edit'])->name('subscriptionPlan.edit');
        Route::post('/subscription-plans/{subscriptionPlan}/edit', [SubscriptionPlanController::class, 'update'])->name('subscriptionPlan.update');
        Route::get('/subscription-plans/{subscriptionPlan}/delete', [SubscriptionPlanController::class, 'delete'])->name('subscriptionPlan.delete');
        Route::get('/subscription-plans/{subscriptionPlan}/toggle', [SubscriptionPlanController::class, 'toggle'])->name('subscriptionPlan.status.toggle');

        Route::get('/web-settings', [WebSettingController::class, 'index'])->name('webSetting.index');
        Route::post('/web-settings/{webSetting?}', [WebSettingController::class, 'update'])->name('webSetting.update');
        Route::get('/web-settings/{webSetting}/toggle', [WebSettingController::class, 'toggle'])->name('webSetting.toggle');
        Route::get('/web-settings/{webSetting}/toggle-ads', [WebSettingController::class, 'AdsToggle'])->name('webSetting.toggle.ads');

        Route::get('/sms-config', [SMSConfigurationController::class, 'index'])->name('smsConfig.index');
        Route::post('/sms-config', [SMSConfigurationController::class, 'update'])->name('smsConfig.update');

        Route::get('/payment-config', [PaymentConfigurationController::class, 'index'])->name('paymentConfig.index');
        Route::match(['post', 'put'], '/payment-config', [PaymentConfigurationController::class, 'update'])->name('paymentConfig.update');

        Route::get('/mail-config', [MailConfigurationController::class, 'index'])->name('mailConfig.index');
        Route::post('/mail-config', [MailConfigurationController::class, 'update'])->name('mailConfig.update');

        Route::get('/fcm-config', [FCMController::class, 'index'])->name('fcm.index');
        Route::match(['post', 'put'], '/fcm-config', [FCMController::class, 'update'])->name('fcm.update');

        Route::get('/settings/{slug}', [SettingController::class, 'show'])->name('setting.show');
        Route::get('/settings/{slug}/edit', [SettingController::class, 'edit'])->name('setting.edit');
        Route::match(['post', 'put'], '/settings/{setting}', [SettingController::class, 'update'])->name('setting.update');

        Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
        Route::get('/profile/edit', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::post('/profile/edit', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/profile/change-password', function () {
            return view('profile.change-password');
        })->name('profile.change-password');
        Route::post('/profile/change-password', [ProfileController::class, 'changePassword'])->name('profile.change-password.update');
    });

    Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
    Route::get('/payment/{gateway}', [PaymentController::class, 'payment'])->name('payment');
    Route::get('/payment/paypal/success/{transaction_id}', [PaymentController::class, 'paymentSuccess'])->name('paypal.payment.success');
    Route::get('/payment/paypal/cancel', [PaymentController::class, 'paymentCancel'])->name('paypal.payment.cancel');
    Route::get('/payment/stripe/success/{transaction_id}', [PaymentController::class, 'stripePaymentSuccess'])->name('stripe.payment.success');
    Route::get('/payment/stripe/cancel', [PaymentController::class, 'stripePaymentCancel'])->name('stripe.payment.cancel');
    Route::get('/payment/razorpay/checkout', [PaymentController::class, 'razorpayCheckout'])->name('razorpay.payment.checkout');
    Route::get('/payment/razorpay/verify', [PaymentController::class, 'razorpayVerify'])->name('razorpay.payment.verify');
    Route::match(['get', 'post'], '/payment/aamarpay/success', [PaymentController::class, 'aamarpayPaymentSuccess'])->name('aamrpay.payment.success');
    Route::match(['get', 'post'], '/payment/aamarpay/fail', [PaymentController::class, 'aamarpayPaymentFail'])->name('aamrpay.payment.fail');
    Route::match(['get', 'post'], '/payment/aamarpay/cancel', [PaymentController::class, 'aamarpayPaymentCancel'])->name('aamrpay.payment.cancel');
    Route::get('/payment/success', [PaymentController::class, 'successPayment'])->name('payment.success');
    Route::get('/payment/response/success', [PaymentController::class, 'success'])->name('payment.success.response');
    Route::get('/payment/cancel', [PaymentController::class, 'cancelPayment'])->name('payment.cancel');
});
