<?php

namespace App\Models;

use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles, SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'trial_started_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];

    /** Length of the free trial, in days. */
    public const TRIAL_DAYS = 7;

    //==========================================> Trial (server-authoritative)
    /**
     * Stamp the trial start on the user's FIRST authentication if not already
     * set. Being server-side, the 7-day trial cannot be reset by reinstalling
     * the app or signing out — it is bound to the account.
     */
    public function ensureTrialStarted(): void
    {
        if ($this->trial_started_at === null) {
            $this->trial_started_at = now();
            $this->save();
        }
    }

    /** When the free trial ends, or null if it never started. */
    public function trialEndsAt(): ?\Illuminate\Support\Carbon
    {
        return $this->trial_started_at
            ? $this->trial_started_at->copy()->addDays(self::TRIAL_DAYS)
            : null;
    }

    //==========================================> Relations
    public function media()
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function favorites()
    {
        return $this->belongsToMany(PlayList::class, 'favorites');
    }

    public function devices()
    {
        return $this->hasMany(DeviceKey::class);
    }

    //=========================================> Attributes
    public function thumbnail(): Attribute
    {
        $media = $this->media;
        $image = asset('images/dummy-profile.png');

        if($media && Storage::exists($media->src)){
            $image = Storage::url($media->src);
        }

        return new Attribute(
            get: fn() => $image
        );
    }

}
