<?php

namespace App\Services;
use App\Factories\SmsGatewayFactory;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class NotificationServices
{
    public function __construct(
        public string $message,
        public array $tokens,
        public $title = 'Maditam',
        public $body = null
    ) {
         $this->sendNotification();
    }

    public function sendNotification()
    {
        $notification = Notification::create($this->title, $this->body ?? $this->message);

        $firebaseCredentials = storage_path('app/public/firebase_credentials.json');

        if (!file_exists($firebaseCredentials)) {
            logger()->error('Firebase credentials file not found: ' . $firebaseCredentials);
            return false;
        }

        $messaging = (new Factory)->withServiceAccount($firebaseCredentials)->createMessaging();

        // High-priority delivery + explicit channel so the OS shows a heads-up
        // banner in the status bar (Android 8+ requires a channel; iOS needs the
        // apns priority/sound). A data block lets the app render/cache it too.
        $message = CloudMessage::new()
            ->withNotification($notification)
            ->withAndroidConfig(AndroidConfig::fromArray([
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'high_importance_channel',
                    'sound' => 'default',
                    'default_sound' => true,
                ],
            ]))
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => ['apns-priority' => '10'],
                'payload' => [
                    'aps' => ['sound' => 'default', 'badge' => 1],
                ],
            ]))
            ->withData([
                'type' => 'admin',
                'title' => (string) $this->title,
                'body' => (string) ($this->body ?? $this->message),
            ]);

        // Drop empty tokens defensively (empty strings cause whole-batch errors).
        $tokens = array_values(array_filter($this->tokens, fn ($t) => !empty($t)));
        if (empty($tokens)) {
            logger()->warning('FCM: no valid device tokens to send to.');
            return false;
        }

        try {
            $report = $messaging->sendMulticast($message, $tokens);
            logger()->info('FCM multicast: ' . $report->successes()->count() . ' success, ' . $report->failures()->count() . ' failures, tokens: ' . count($tokens));
            foreach ($report->failures()->getItems() as $failure) {
                logger()->error('FCM failure: ' . $failure->error()->getMessage() . ' | token: ' . substr($failure->target()->value(), 0, 20));
            }
        } catch (\Exception $e) {
            logger()->error('FCM exception: ' . $e->getMessage());
        }
    }

    public static function sendSmsNotification($phoneNumber, $message)
    {
        $smsService = SmsGatewayFactory::make();
        $smsService->sendSms($phoneNumber, $message);
    }
}
