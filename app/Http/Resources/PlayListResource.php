<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use App\Models\WebSetting;
use App\Repositories\AlbamRepository;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;

class PlayListResource extends JsonResource
{
    // Keep in sync with MediaStreamController::URL_TTL_MINUTES.
    private const URL_TTL_MINUTES = 360;

    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $favorites = [];
        $has_subscribed = false;
        $user = Auth::guard('api')->user();
        if ($user) {
            $favorites = $user->favorites()->pluck('id')->toArray();
            $subscription = Subscription::hasSubscribed($user);
            $websetting = WebSetting::first();
            $has_subscribed = $websetting?->subscription ? true : ($subscription ? true : false);
        }

        // Media URLs are never the raw storage path. Free content (or content
        // the caller is entitled to) gets a short-lived signed streaming URL;
        // locked premium content gets null so it can't be played or scraped.
        $canPlay = !$this->is_paid || $has_subscribed;
        $audioUrl = ($canPlay && $this->audio_id) ? $this->signedStreamUrl('audio') : null;
        $videoUrl = ($canPlay && $this->video_id) ? $this->signedStreamUrl('video') : null;

        $albam = (new AlbamRepository())->findById($request->albam);
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'duration' => $this->duration,
            'thumbnail' => $this->thumbnail,
            'audio' => $audioUrl,
            'video' => $videoUrl,
            'content_type' => $this->content_type ?? 'audio',
            'views' => $this->views,
            'is_favorite' => in_array($this->id, $favorites),
            'is_paid' => $has_subscribed ? false : $this->is_paid,
            'albam' => AlbamResource::make($albam),
            'has_readmore' => $this->readmore ? true : false,
        ];
    }

    private function signedStreamUrl(string $type): string
    {
        return URL::temporarySignedRoute(
            'media.stream',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['playlist' => $this->id, 'type' => $type]
        );
    }
}
