<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PlayList;
use App\Models\Subscription;
use App\Models\WebSetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Gated delivery of playlist media.
 *
 * Premium media URLs are never embedded in list responses. Instead the client
 * asks {@see playUrl()} for a short-lived signed URL at play time; that call is
 * authenticated and checks entitlement. The signed {@see stream()} route then
 * serves the bytes (with HTTP range support for seeking) and expires quickly,
 * so links can't be permanently shared.
 */
class MediaStreamController extends Controller
{
    private const URL_TTL_MINUTES = 360; // 6h — long enough to finish playback

    /** Issues a signed streaming URL after verifying the caller may play this item. */
    public function playUrl(Request $request, PlayList $playlist)
    {
        $type = $this->resolveType($request, $playlist);

        if ($playlist->is_paid && !$this->userIsEntitled()) {
            return $this->json('Subscription required to play this content.', [], Response::HTTP_FORBIDDEN);
        }

        if (!$this->mediaFor($playlist, $type)) {
            return $this->json('Media not available.', [], Response::HTTP_NOT_FOUND);
        }

        $url = URL::temporarySignedRoute(
            'media.stream',
            now()->addMinutes(self::URL_TTL_MINUTES),
            ['playlist' => $playlist->id, 'type' => $type]
        );

        return $this->json('Playback URL', [
            'url' => $url,
            'type' => $type,
            'expires_in' => self::URL_TTL_MINUTES * 60,
        ]);
    }

    /** Streams the bytes. Reachable only with a valid (unexpired) signature. */
    public function stream(Request $request, PlayList $playlist)
    {
        $type = $request->query('type') === 'video' ? 'video' : 'audio';

        $media = $this->mediaFor($playlist, $type);
        if (!$media) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $absolute = $this->absolutePath($media->src);
        if ($absolute === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        // BinaryFileResponse honours Range requests, enabling seek/buffering.
        // Cacheable for the life of the signed URL (6h TTL) so the device and any
        // edge cache can reuse the bytes instead of re-fetching from origin cold
        // on every play — the previous `no-store` forced a cold origin hit each
        // time, which made videos slow to start. The URL's signature is the
        // access token and it expires, so caching keyed by the full URL is safe.
        return response()->file($absolute, [
            'Content-Type' => $this->contentType($media, $type),
            'Cache-Control' => 'public, max-age=21600',
        ])->setAutoLastModified();
    }

    private function resolveType(Request $request, PlayList $playlist): string
    {
        $requested = $request->query('type');
        if ($requested === 'audio' || $requested === 'video') {
            return $requested;
        }
        return ($playlist->content_type ?? 'audio') === 'video' ? 'video' : 'audio';
    }

    private function mediaFor(PlayList $playlist, string $type)
    {
        return $type === 'video' ? $playlist->video : $playlist->audio;
    }

    /** Mirrors the entitlement rule used across the API (global flag OR active paid sub). */
    private function userIsEntitled(): bool
    {
        $user = auth()->user();
        if (!$user) {
            return false;
        }
        if (WebSetting::first()?->subscription) {
            return true;
        }
        return (bool) Subscription::hasSubscribed($user);
    }

    /** Locates the media file across the disks the project uses for uploads. */
    private function absolutePath(?string $src): ?string
    {
        if (empty($src)) {
            return null;
        }
        if (Storage::disk('public')->exists($src)) {
            return Storage::disk('public')->path($src);
        }
        if (Storage::exists($src)) {
            return Storage::path($src);
        }
        if (File::exists(public_path($src))) {
            return public_path($src);
        }
        return null;
    }

    private function contentType($media, string $type): string
    {
        $ext = strtolower((string) ($media->extension ?? pathinfo($media->src, PATHINFO_EXTENSION)));
        return match ($ext) {
            'mp3' => 'audio/mpeg',
            'm4a', 'aac' => 'audio/aac',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'mp4', 'm4v' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => $type === 'video' ? 'video/mp4' : 'audio/mpeg',
        };
    }
}
