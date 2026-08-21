<?php

namespace App\Repositories;

use App\Http\Requests\SignupRequest;
use App\Http\Requests\SocialLoginRequest;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserRepository extends Repository
{

    /**
     * base method
     *
     * @method model()
     */
    public $path = 'images/customers/';
    public function model()
    {
        return User::class;
    }

    public function getAll()
    {
        $searchKey = request()->search;
        $users = $this->model()::role('user');
        if ($searchKey) {
            $users = $users->where('name', 'like', "%$searchKey%")
                ->orWhere('email', 'like', "%$searchKey%");
        }
        return $users->paginate(10);
    }

    public function getAccessToken(User $user)
    {
        $token = $user->createToken('user token');
        return [
            'auth_type' => 'Bearer',
            'token' => $token->accessToken,
            'expires_at' => $token->token->expires_at->format('Y-m-d H:i:s'),
        ];
    }

    public function findByEmail($email): User|null
    {
        $user = $this->query()->where('email', $email)->first();
        return $user;
    }

    public function storeByRequest(SignupRequest $request): User
    {
        return $this->create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);
    }

    public function storeBySocialLoginRequest(SocialLoginRequest $request): User
    {
        // Use null (not '') for the providers that don't apply and for a missing
        // email (Apple "Hide My Email"). The users.email column is unique, and
        // MySQL allows many NULLs but only one ''. Storing '' would make the
        // SECOND hidden-email user collide on the unique index and 500.
        $user = $this->create([
            // Guard against a null/blank name (returning Apple users send none,
            // and the column is NOT NULL): fall back to the email local-part, or
            // a generic label, so account creation can't 500.
            'name' => !empty($request->name)
                ? $request->name
                : (!empty($request->email)
                    ? explode('@', $request->email)[0]
                    : ucfirst($request->type) . ' User'),
            'email' => !empty($request->email) ? $request->email : null,
            'facebook_id' => $request->type == 'facebook' ? $request->id : null,
            'google_id' => $request->type == 'google' ? $request->id : null,
            'apple_id' => $request->type == 'apple' ? $request->id : null,
            'email_verified_at' => Carbon::now()
        ]);

        $user->assignRole('user');

        return $user;
    }

    public function updateByRequest(Request $request, User $user): User
    {
        // for dev mode
        if ($request->subscription) {
            $subs = SubscriptionPlan::find($request->subscription);
            Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $subs->id,
                'amount' => $subs->amount,
                'expired_at' => Carbon::now()->addDays($subs->duration)
            ]);
        } else if ($user->subscriptions()) {
            $user->subscriptions()->update([
                'is_canceld' => 1,
            ]);
        }
        $thumbnail = $this->profileImageUpdate($request, $user);
        $user->update([
            'media_id' => $thumbnail ? $thumbnail->id : null,
            'name' => $request->name,
            'email' => $request->email
        ]);
        return $user;
    }

    private function profileImageUpdate($request, $user)
    {
        $thumbnail = $user->media;
        if ($request->hasFile('profile_photo') && $thumbnail == null) {
            $thumbnail = (new MediaRepository())->storeByRequest(
                $request->profile_photo,
                $this->path,
                'image'
            );
        }

        if ($request->hasFile('profile_photo') && $thumbnail) {
            $thumbnail = (new MediaRepository())->updateByRequest(
                $request->profile_photo,
                $thumbnail,
                $this->path,
                'image',
            );
        }

        return $thumbnail;
    }

}

