<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds store-purchase / receipt-verification fields to subscriptions so that
 * entitlement can be granted only after server-side validation of an Apple or
 * Google purchase (or a confirmed web payment-gateway callback).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            // apple | google | web. Existing web rows stay null and behave as before.
            if (!Schema::hasColumn('subscriptions', 'platform')) {
                $table->string('platform')->nullable()->after('subscription_plan_id');
            }
            // Store product identifier (e.g. com.hearttomind.premium.annual).
            if (!Schema::hasColumn('subscriptions', 'product_id')) {
                $table->string('product_id')->nullable()->after('platform');
            }
            // Per-purchase transaction id reported by the store.
            if (!Schema::hasColumn('subscriptions', 'provider_transaction_id')) {
                $table->string('provider_transaction_id')->nullable()->after('product_id');
            }
            // Stable id across renewals (Apple original_transaction_id / Google purchaseToken-derived).
            if (!Schema::hasColumn('subscriptions', 'original_transaction_id')) {
                $table->string('original_transaction_id')->nullable()->after('provider_transaction_id');
            }
            // Google purchase token (needed for re-verification / acknowledgement).
            if (!Schema::hasColumn('subscriptions', 'purchase_token')) {
                $table->text('purchase_token')->nullable()->after('original_transaction_id');
            }
            // Raw verification response retained for audit / dispute handling.
            if (!Schema::hasColumn('subscriptions', 'verification_payload')) {
                $table->json('verification_payload')->nullable()->after('purchase_token');
            }
            // active | expired | refunded | cancelled. Null = legacy/web row (treated as active when paid+unexpired).
            if (!Schema::hasColumn('subscriptions', 'status')) {
                $table->string('status')->nullable()->after('verification_payload');
            }

            $table->index(['user_id', 'is_paid', 'expired_at']);
        });

        // Idempotency: one row per store transaction. Done as a separate
        // statement so partial/null transaction ids (web rows) are allowed.
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unique(['platform', 'original_transaction_id'], 'subscriptions_platform_txn_unique');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropUnique('subscriptions_platform_txn_unique');
            $table->dropIndex(['user_id', 'is_paid', 'expired_at']);
            $table->dropColumn([
                'platform',
                'product_id',
                'provider_transaction_id',
                'original_transaction_id',
                'purchase_token',
                'verification_payload',
                'status',
            ]);
        });
    }
};
