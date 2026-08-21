<?php

namespace App\Http\Controllers\API;

use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Http\Controllers\Controller;
use App\Http\Requests\SigninRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Events\EmailVerificationEvent;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SocialLoginRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\Subscription;
use App\Models\User;
use App\Repositories\DeviceKeyRepository;
use App\Repositories\VerificationRepository;

class AuthController extends Controller
{

    public function __construct(public UserRepository $userRepo)
    {
    }

    public function signIn(SigninRequest $request)
    {
        $websetting = WebSetting::first();
        $is_expired = false;
        if ($user = $this->authenticate($request)) {

            $subscriptions = $user->subscriptions()->where('is_paid', '=', 1)->get();

            if($subscriptions->count()>0){
                $is_expired = Subscription::hasSubscribed($user);
            }

            if ($request->device_key) {
                (new DeviceKeyRepository())->storeByRequest($user, $request);
            }

            $user->ensureTrialStarted();
            $active = Subscription::activeFor($user);
            return $this->json('Log In Successful', [
                'has_subscribed' => $websetting?->subscription ? true : ($is_expired ? true : false),
                'subscription_expires_at' => optional($active)->expired_at?->toIso8601String(),
                'trial_ends_at' => optional($user->trialEndsAt())->toIso8601String(),
                'user' => new UserResource($user),
                'access' => $this->userRepo->getAccessToken($user)
            ]);
        }
        return $this->json('Credential is invalid!', [], Response::HTTP_BAD_REQUEST);
    }

    public function signUp(SignupRequest $request)
    {
        $user = $this->userRepo->storeByRequest($request);

        $user->assignRole('user');

        if ($request->device_key) {
            (new DeviceKeyRepository())->storeByRequest($user, $request);
        }

        $user->ensureTrialStarted();
        // EmailVerificationEvent::dispatch($user);
        return $this->json('Log In Successful', [
            'has_subscribed' => false,
            'subscription_expires_at' => null,
            'trial_ends_at' => optional($user->trialEndsAt())->toIso8601String(),
            'user' => new UserResource($user),
            'access' => $this->userRepo->getAccessToken($user)
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request)
    {
        $user = (new UserRepository())->findByEmail($request->email);
        $verification = (new VerificationRepository())->findOrCreate($user->email);
        // return EmailVerificationEvent::dispatch($user);
        return $verification;

        // return $this->json('Please check you email, We send a OTP');
    }

    public function otpVerify(VerifyOtpRequest $request)
    {
        $verification = (new VerificationRepository())->findByEmail($request->email);
        if ($verification->otp == $request->otp) {
            return $this->json('This is your password reset token', [
                'reset_token' => $verification->token
            ]);
        }

        return $this->json('Invalid OTP');
    }

    public function resetPassword(ResetPasswordRequest $request)
    {
        $verification = (new VerificationRepository())->findByToken($request->reset_token);

        if ($verification) {
            $user = (new UserRepository())->findByEmail($verification->email);
            $user->update([
                'password' => Hash::make($request->password)
            ]);

            $verification->delete();
            return $this->json('Password is reseted successfully');
        }

        return $this->json('Invalid Request', Response::HTTP_BAD_REQUEST);
    }

    public function tokenResend()
    {
        $user = auth()->user();

        if (!$user->email_verified_at) {
            $verification = (new VerificationRepository())->findOrCreate($user->email);
            EmailVerificationEvent::dispatch($user);
            // return $verification;
            return $this->json('Please check you email. We have sent a verification email.');
        }
        return $this->json('Already verified');
    }

    public function logout()
    {
        $user = auth()->user();
        if (\request()->device_key) {
            (new DeviceKeyRepository())->destroy(\request()->device_key);
        }
        if ($user) {
            $user->token()->revoke();
            return $this->json('Logged out successfully!');
        }
        return $this->json('No Logged in user found', [], Response::HTTP_UNAUTHORIZED);
    }

    private function authenticate(SigninRequest $request)
    {
        $user = $this->userRepo->findByEmail($request->email);

        if (!is_null($user) && Hash::check($request->password, $user->password)) {
            return $user;
        }

        return false;
    }

    public function socialLogin(SocialLoginRequest $request)
    {

        $type = $request->type;

        if ($type) {
            // 1) Match the SAME provider identity first (stable per user/app).
            //    This is what makes a repeat Apple/Google login reuse the
            //    existing backend user, even with "Hide My Email".
            $user = $this->userRepo->query()
                ->where($type . '_id', $request->id)->first();

            // 2) Fall back to email so the same person who previously signed in
            //    with a different method (or email/password) is NOT duplicated —
            //    link this provider id onto the existing account instead.
            if (!$user && !empty($request->email)) {
                $existing = $this->userRepo->query()
                    ->where('email', $request->email)->first();
                if ($existing) {
                    $existing->update([$type . '_id' => $request->id]);
                    $user = $existing;
                }
            }

            // 3) Genuinely new identity → create the account.
            if (!$user) {
                $user = $this->userRepo->storeBySocialLoginRequest($request);
            }

            if ($request->device_key) {
                (new DeviceKeyRepository())->storeByRequest($user, $request);
            }

            $user->ensureTrialStarted();
            $websetting = WebSetting::first();
            $active = Subscription::activeFor($user);

            return $this->json('Log In Successful', [
                'has_subscribed' => $websetting?->subscription ? true : ((bool) $active),
                'subscription_expires_at' => optional($active)->expired_at?->toIso8601String(),
                'trial_ends_at' => optional($user->trialEndsAt())->toIso8601String(),
                'user' => new UserResource($user),
                'access' => $this->userRepo->getAccessToken($user)
            ]);
        }

        return $this->json('Invalid Type', [], Response::HTTP_BAD_REQUEST);
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $idToken = $request->id_token;

        $tokenInfo = Http::get("https://oauth2.googleapis.com/tokeninfo?id_token={$idToken}");

        if ($tokenInfo->failed() || $tokenInfo->json('email') === null) {
            return $this->json('Invalid Google token', [], Response::HTTP_UNAUTHORIZED);
        }

        $payload = $tokenInfo->json();
        $email   = $payload['email'];
        $name    = $payload['name'] ?? ($payload['given_name'] ?? 'Google User');
        $googleId = $payload['sub'];

        $user = User::where('google_id', $googleId)->orWhere('email', $email)->first();

        if (!$user) {
            $user = User::create([
                'name'              => $name,
                'email'             => $email,
                'google_id'         => $googleId,
                'password'          => Hash::make(Str::random(24)),
                'email_verified_at' => now(),
            ]);
            $user->assignRole('user');
        } elseif (!$user->google_id) {
            $user->update(['google_id' => $googleId]);
        }

        if ($request->device_key) {
            (new DeviceKeyRepository())->storeByRequest($user, $request);
        }

        $websetting  = WebSetting::first();
        $is_expired  = false;
        $subscriptions = $user->subscriptions()->where('is_paid', '=', 1)->get();
        if ($subscriptions->count() > 0) {
            $is_expired = Subscription::hasSubscribed($user);
        }

        return $this->json('Log In Successful', [
            'has_subscribed' => $websetting?->subscription ? true : ($is_expired ? true : false),
            'user'   => new UserResource($user),
            'access' => $this->userRepo->getAccessToken($user),
        ]);
    }

    public function emailVerify(Request $request)
    {
        $request->validate([
            'otp' => 'required'
        ]);
        $verification = (new VerificationRepository())->findByOtp($request->otp);
        if ($verification) {
            $user = (new UserRepository())->findByEmail($verification->email);
            $user->update([
                'email_verified_at' => now()
            ]);
            $verification->delete();
            return $this->json('Email verified successfully');
        }
        return $this->json('Invalid OTP');
    }
}
