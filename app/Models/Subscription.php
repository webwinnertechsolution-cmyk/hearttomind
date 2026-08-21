<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $casts = [
        'expired_at' => 'datetime',
        'is_paid' => 'boolean',
        'verification_payload' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    // ========================== proxy

    /**
     * Returns the id of the user's currently-active, PAID subscription, or false.
     *
     * Entitlement is server-authoritative: a subscription only counts when it
     * has been marked paid (is_paid = 1, set exclusively by a verified store
     * purchase or a confirmed payment-gateway callback) AND has not expired AND
     * has not been cancelled/refunded. Unpaid rows — e.g. abandoned gateway
     * orders or client-side `/subscriptions/store` calls — never grant access.
     */
    public static function hasSubscribed($user): bool|int
    {
        $subscription = self::activeFor($user);
        return $subscription ? $subscription->id : false;
    }

    /**
     * The user's active paid subscription with the furthest expiry, or null.
     */
    public static function activeFor($user): ?self
    {
        if (!$user) {
            return null;
        }

        return self::query()
            ->where('user_id', $user->id)
            ->where('is_paid', 1)
            ->where(function ($q) {
                $q->whereNull('status')->orWhere('status', '!=', 'refunded');
            })
            ->where('expired_at', '>=', now())
            ->orderByDesc('expired_at')
            ->first();
    }
}
