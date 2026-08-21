<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\DeviceKey;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;
use App\Services\NotificationServices;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $users = (new UserRepository())->query()->role('user')->get();

        return view('notification.index', compact('users'));
    }

    // public function sendNotification(Request $request)
    // {
    //     $request->validate([
    //         'title' => 'required|string',
    //         'message' => 'required',
    //         'user' => 'required|array'
    //     ]);

    //     $message = $request->message;
    //     $title = $request->title;
    //     $users = $request->user;

    //     $keys = DeviceKey::whereIn('user_id', $users)->pluck('key')->toArray();

    //     try {
    //         (new NotificationServices($message, $keys, $title));
    //     } catch (\Exception $e) {
    //         logger()->error('Notification send failed: ' . $e->getMessage());
    //     }

    //     foreach ($users as $userID) {
    //         (new NotificationRepository())->store($userID, $message, $title);
    //     }

    //     return back()->with('success', 'Notification sent successfully');
    // }
    




public function sendNotification(Request $request)
{
    $request->validate([
        'title' => 'required|string',
        'message' => 'required',
        'user' => 'required|array'
    ]);

    $message = $request->message;
    $title = $request->title;
    $users = $request->user;

    $keys = DeviceKey::whereIn('user_id', $users)->pluck('key')->toArray();
    try {
        (new NotificationServices($message, $keys, $title));
    } catch (\Exception $e) {
        logger()->error('Notification send failed: ' . $e->getMessage());
    }

    foreach ($users as $userID) {
        (new NotificationRepository())->store($userID, $message, $title);
    }
\Config::set('mail.mailers.smtp.verify_peer', false);
    // Email bhejo
    $userEmails = \App\Models\User::whereIn('id', $users)->get();
    foreach ($userEmails as $user) {
        \Mail::to($user->email)->send(new \App\Mail\NotificationMail($title, $message));
    }

    return back()->with('success', 'Notification sent successfully');
}


    public function userFilters()
    {
        $userType = request()->user_type;
        $deviceType = request()->device_type;

        $users = (new UserRepository())->query()->role('user');

        if ($userType == 'premium') {
            $users = $users->whereHas('subscriptions');
        }

        if ($userType == 'free') {
            $users = $users->doesntHave('subscriptions');
        }

        if ($deviceType && $deviceType != 'all') {
            $users = $users->whereHas('devices', function ($devices) use ($deviceType) {
                $devices->where('device_type', $deviceType);
            });
        }

        return $this->json('user list', [
            'users' => UserResource::collection($users->get())
        ]);
    }
}

