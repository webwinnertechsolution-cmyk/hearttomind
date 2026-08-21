<?php

namespace App\Http\Controllers\API;

use App\Models\Subscription;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Repositories\UserRepository;
use Illuminate\Support\Facades\Hash;
use App\Repositories\MediaRepository;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Requests\updatePasswordRequest;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    private $path = 'images/profiles/';
    public function __construct(public MediaRepository $mediaRepo, public UserRepository $userRepo)
    {}

    public function index()
    {
        $user = auth()->user();
        $is_expired = false;
        $subscriptions = $user->subscriptions()->where('is_paid', '=', 1)->get();

            if($subscriptions->count()>0){
                $is_expired = Subscription::hasSubscribed($user);
            }

        $user->ensureTrialStarted();
        $websetting = WebSetting::first();
        $active = Subscription::activeFor($user);

        return $this->json('Your profile information', [
            'has_subscribed' => $websetting?->subscription ? true : ($is_expired ? true : false),
            'subscription_expires_at' => optional($active)->expired_at?->toIso8601String(),
            'trial_ends_at' => optional($user->trialEndsAt())->toIso8601String(),
            'user' => UserResource::make($user),
        ]);
    }

    public function updateProfile(ProfileUpdateRequest $request)
    {
        $user = $this->userRepo->find(auth()->id());
        $media = $user->media;

        if($media){
            $this->mediaRepo->updateByRequest($request->image, $media, $this->path, 'image');
            return $this->json('The profile photo is updated succesfully', [
                'user' => UserResource::make($user)
            ]);
        }

        $media = $this->mediaRepo->storeByRequest($request->image, $this->path, 'image');

        $this->userRepo->update($user, [
            'media_id' => $media->id
        ]);

        return $this->json('The user profile is uploaded successfully', [
            'user' => UserResource::make($user)
        ]);
    }

    public function passwordUpdate(updatePasswordRequest $request)
    {
        $user = $this->userRepo->find(auth()->id());

        if(!Hash::check($request->current_password, $user->password)){
            return $this->json("Sorry, Your current password doesn't match", [], Response::HTTP_BAD_REQUEST);
        }

        $this->userRepo->update($user, [
            'password' => Hash::make($request->password)
        ]);

        return $this->json('The password is updated successfully');
    }

    /** Per-user listening stats (survive app reinstall — stored server-side). */
    public function stats()
    {
        $user = auth()->user();
        return $this->json('Your stats', [
            'sessions_played' => (int) ($user->total_sessions_played ?? 0),
            'tracks_played'   => (int) ($user->total_tracks_played ?? 0),
        ]);
    }

    /** Atomically add to the per-user stats. Body: sessions (int), tracks (int). */
    public function incrementStats(Request $request)
    {
        $user = auth()->user();
        $sessions = max(0, (int) $request->input('sessions', 0));
        $tracks   = max(0, (int) $request->input('tracks', 0));
        if ($sessions > 0 || $tracks > 0) {
            DB::table('users')->where('id', $user->id)->update([
                'total_sessions_played' => DB::raw('total_sessions_played + ' . $sessions),
                'total_tracks_played'   => DB::raw('total_tracks_played + ' . $tracks),
            ]);
            $user->refresh();
        }
        return $this->json('Stats updated', [
            'sessions_played' => (int) ($user->total_sessions_played ?? 0),
            'tracks_played'   => (int) ($user->total_tracks_played ?? 0),
        ]);
    }

    public function deleteProfile()
    {
        $user = auth()->user();
        $user->token()->revoke();
        $user->delete();
        return $this->json('Profile is deleted successfully');
    }
}
