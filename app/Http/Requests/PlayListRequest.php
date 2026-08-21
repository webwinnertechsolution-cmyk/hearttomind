<?php

namespace App\Http\Requests;

use App\Models\Albam;
use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;

class PlayListRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        $isUpdate = request()->routeIs('playlist.update');
        $isVideo = request('content_type') === 'video';

        if ($isUpdate) {
            $audioRule = 'nullable|mimes:mp3,ogg,wav';
            $videoRule = 'nullable|mimes:mp4,mov,avi,mkv,webm';
        } else {
            // On create: audio required only for music tracks, video required only for video tracks
            $audioRule = $isVideo ? 'nullable|mimes:mp3,ogg,wav' : 'required|mimes:mp3,ogg,wav';
            $videoRule = $isVideo ? 'required|mimes:mp4,mov,avi,mkv,webm' : 'nullable|mimes:mp4,mov,avi,mkv,webm';
        }

        return [
            'name' => 'required|string',
            'duration' => 'required',
            'category' => 'nullable|exists:' . (new Category())->getTable() . ',id',
            'albam' => 'nullable|exists:' . (new Albam())->getTable() . ',id',
            'description' => 'nullable|string',
            'thumbnail' => ['nullable', 'mimes:png,jpg,jpeg,svg,gif'],
            'content_type' => 'nullable|in:audio,video',
            'audio' => $audioRule,
            'video' => $videoRule,
            'active' => 'nullable'
        ];
    }
}
