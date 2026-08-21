<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlbamResource;
use App\Models\Category;
use App\Repositories\AlbamRepository;
use App\Repositories\CategoryRepository;
use Illuminate\Http\Request;

class AlbamController extends Controller
{
    public function __construct(public AlbamRepository $albamRepo)
    {}

    public function index(Request $request)
    {
        $request->validate([
            'category' => 'required|exists:'.(new Category())->getTable().',id'
        ]);

        $category = (new CategoryRepository())->findById($request->category);

        // Stable ordering is required for correct pagination.
        $query = $category->albams()->active()
            ->orderBy('albams.display_order', 'asc')
            ->orderBy('albams.id', 'asc');

        // Backward-compatible: paginate only when a page is requested; otherwise
        // return the full list exactly as before (keeps older app builds working).
        if ($request->filled('page')) {
            $perPage = (int) $request->input('per_page', 20);
            $paginator = $query->paginate($perPage);
            return $this->json('albam list', [
                'albams' => AlbamResource::collection($paginator->items()),
                'pagination' => $this->paginationMeta($paginator),
            ]);
        }

        return $this->json('albam list', [
            'albams' => AlbamResource::collection($query->get())
        ]);
    }
}
