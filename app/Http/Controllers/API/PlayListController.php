<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlayListResource;
use App\Http\Resources\ReadmoreResource;
use App\Models\PlayList;
use App\Repositories\AlbamRepository;
use App\Repositories\PlayListRepository;
use Illuminate\Support\Facades\Auth;

class PlayListController extends Controller
{
    public function __construct(public PlayListRepository $playListRepo)
    {
    }

    public function index()
    {
        $request = \request();
        $albam = (new AlbamRepository())->findById($request->albam);

        // Guard against a missing/invalid album id instead of 500-ing.
        if (!$albam) {
            return $this->json('play lists', ['albams' => []]);
        }

        // Stable ordering is required for correct pagination.
        $query = $albam->playlists()->active()
            ->orderBy('play_lists.display_order', 'asc')
            ->orderBy('play_lists.id', 'asc');

        // Backward-compatible: paginate only when a page is requested.
        if ($request->filled('page')) {
            $perPage = (int) $request->input('per_page', 20);
            $paginator = $query->paginate($perPage);
            return $this->json('play lists', [
                'albams' => PlayListResource::collection($paginator->items()),
                'pagination' => $this->paginationMeta($paginator),
            ]);
        }

        return $this->json('play lists', [
            'albams' => PlayListResource::collection($query->get())
        ]);
    }

    public function viewCount($id)
    {

        $playlist = $this->playListRepo->query()->where('id', $id)->active()->firstOrFail();
        $playlist->increment('views');
        return $this->json('podcast views', [
            'views' => $playlist->views
        ]);
    }

    public function readmore(PlayList $playlist)
    {

        return $this->json('play lists content', [
            'readmore' => $playlist->readmore ? ReadmoreResource::make($playlist->readmore) : null
        ]);
    }
}
