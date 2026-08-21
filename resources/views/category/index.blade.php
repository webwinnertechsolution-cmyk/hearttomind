@extends('layouts.app')

@push('title')
<div class="page-title-box">
    <h4 class="page-title">Categories</h4>
    <ol class="breadcrumb">
        <li class="breadcrumb-item active">All categorys list</li>
    </ol>
</div>
@endpush

@section('content')
<div class="mb-3 d-flex justify-content-between align-items-center">
    <form action="" method="GET" class="d-flex" style="gap:4px">
        <input type="search" name="search" class="form-control" placeholder="Search by category name" value="{{ request()->search }}" style="height: 40px;min-width: 220px" />
        <button class="btn btn-primary btn-sm rounded"><i class="fa fa-search"></i> Search</button>
    </form>
    <a href="{{ route('category.create') }}" class="btn btn-custom">
        <i class="fa fa-plus"></i> Create New
    </a>
</div>
<div class="card mb-2">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th scope="col">Photo</th>
                        <th class="text-center">Icon</th>
                        <th scope="col">Name</th>
                        <th scope="col">Description</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Album</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                    <tr>
                        <td class="text-center">
                            <img src="{{ $category->thumbnail }}" alt="" width="70" height="70" loading="lazy">
                        </td>
                        <td class="text-center">
                            <img src="{{ $category->iconPath }}" alt="" width="50" height="50" loading="lazy">
                        </td>
                        <td>{{ $category->name }}</td>
                        <td>{{ $category->description }}</td>
                        <td class="text-center">
                            <label class="switch">
                                @role('root|admin')
                                <a href="{{ route('category.status.toggle', $category->id) }}">
                                    @else
                                    <a href="javascript:void(0)">
                                        @endrole
                                        <input type="checkbox" {{ $category->status ? 'checked' : '' }}>
                                        <span class="slider round"></span>
                                    </a>
                            </label>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-info btn-sm" data-toggle="modal"
                                data-target="#categoryAlbamId{{ $category->id }}">Albums</button>
                        </td>
                        <td style="min-width: 190px">
                            <div class="d-flex flex-wrap justify-content-center gap-4">
                                <a href="{{ route('category.reorder', [$category->id, 'up']) }}" class="btn btn-outline-secondary btn-sm" title="Move Up"><i class="fa fa-arrow-up"></i></a>
                                <a href="{{ route('category.reorder', [$category->id, 'down']) }}" class="btn btn-outline-secondary btn-sm" title="Move Down"><i class="fa fa-arrow-down"></i></a>
                                <a href="{{ route('category.edit', $category->id) }}" class="btn btn-info btn-sm" data-toggle="tooltip"
                                    title="Edit"><i
                                        class="fa fa-edit"></i></a>
                                @role('admin|root')
                                <a href="{{ route('category.delete', $category->id) }}"
                                    class="btn btn-danger btn-sm delete-confirm"><i class="fa fa-trash" 
                                    data-toggle="tooltip"
                                    title="Delete"></i></a>
                                @else
                                <a href="javascript:void(0)" class="btn btn-danger btn-sm delete-confirm" 
                                    data-toggle="tooltip"
                                    title="Delete"><i
                                        class="fa fa-trash"></i></a>
                                @endrole
                                <a href="{{ route('category.tree', $category->id) }}"
                                    class="btn btn-secondary btn-sm"
                                    data-toggle="tooltip"
                                    title="Create">
                                    <i class="fa fa-tree"></i>
                                </a>
                            </div>
                        </td>
                    </tr>

                    <!-- Modal -->
                    <div class="modal fade" id="categoryAlbamId{{ $category->id }}">
                        <div class="modal-dialog modal-dialog-scrollable modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Album list — {{ $category->name }}</h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="mb-2 d-flex justify-content-between align-items-center">
                                        <span class="text-muted small">{{ $category->albams->count() }} album(s) assigned</span>
                                        <a href="{{ route('albam.create', ['category_id' => $category->id]) }}"
                                           class="btn btn-sm btn-success">
                                            <i class="fa fa-plus"></i> Add Album
                                        </a>
                                    </div>
                                    <div class="d-flex flex-column" style="gap: 8px">

                                        <div class="ModalColumn">
                                            <div>Thumbnail</div>
                                            <span>Name</span>
                                        </div>

                                        @forelse ($category->albams as $albam)
                                        <div class="ModalColumn">
                                            <div>
                                                <img src="{{ $albam->thumbnail }}" alt="thumbnail"
                                                    width="50" loading="lazy">
                                            </div>
                                            <span>{{ $albam->name }}</span>
                                        </div>
                                        @empty
                                        <p class="text-muted text-center mt-2">No albums assigned yet.</p>
                                        @endforelse

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center"> No data found</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
<div class="d-flex justify-content-end">
    {{ $categories->withQueryString()->links() }}
</div>
@endsection