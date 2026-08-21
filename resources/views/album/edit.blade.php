@extends('layouts.app')

@push('title')
    <div class="page-title-box">
        <h4 class="page-title">Album Create</h4>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">Create new album</li>
        </ol>
    </div>
@endpush

@section('content')
    <div class="row">
        <div class="col-lg-10 col-md-12 m-auto">
            @role('root|admin')
                <form action="{{ route('albam.update', $albam->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                @endrole
                <div class="card">
                    <div class="card-header bg-success py-2">
                        <h4 class="text-white">Edit Album</h4>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-lg-8 border-right">
                                <x-input name="name" type="text" placeholder="Name" value="{{ $albam->name }}">
                                </x-input>
                                <div class="form-group">
                                    <label class="mb-1" for="category">Category</label>
                                    <select name="category" id="category" class="form-control @error('category') is-invalid @enderror">
                                        <option value="">-- Select Category --</option>
                                        @foreach ($categories as $cat)
                                            <option value="{{ $cat->id }}" {{ $albam->category_id == $cat->id ? 'selected' : '' }}>
                                                {{ $cat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('category')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>

                                <x-select name="badge" placeholder="Select Badge" value="">
                                    @foreach (\App\Enums\Badge::cases() as $badge)
                                        <option value="{{ $badge->value }}" {{ $albam->badge == $badge->value ? 'selected' : '' }}> {{ $badge->name }} </option>
                                    @endforeach
                                </x-select>
                                <x-textarea name="description" placeholder="Description" value="{{ $albam->description }}"
                                    rows="4"></x-textarea>
                            </div>
                            <div class="col-lg-4">
                                <div class="form-group">
                                    <label class="mb-1">Thumbnail</label>
                                    <input type="file" class="form-control-file" name="thumbnail"
                                        onchange="previewLogoFile(event)" />
                                    <img src="{{ $albam->thumbnail }}" alt="" id="logoPreview" width="150"
                                        height="150" class="mt-1">
                                </div>
                                <div class="d-flex align-items-center" style="gap: 40px">
                                    <div class="d-flex align-items-center" style="gap: 4px">
                                        <input type="checkbox" name="featured" {{ $albam->is_featured ? 'checked' : '' }} id="featured">
                                        <label class="m-0 p-0" for="featured">Featured</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center" style="gap: 40px">
                            <div class="d-flex align-items-center" style="gap: 4px">
                                <input type="checkbox" name="active" id="active" {{ $albam->status ? 'checked' : '' }}>
                                <label class="m-0 p-0" for="active">Active</label>
                            </div>
                            <div class="d-flex align-items-center" style="gap: 4px">
                                <input type="checkbox" name="paid" id="paid" {{ $albam->is_paid ? 'checked' : '' }}>
                                <label class="m-0 p-0" for="paid">Paid</label>
                            </div>
                        </div>

                    </div>
                    <div class="card-footer d-flex justify-content-between bg-white">
                        <a href="{{ route('albam.index') }}" type="button" class="btn btn-secondary text-white"><i
                                class="fa fa-arrow-left"></i> GO Back</a>
                        <button type="submit" class="btn btn-success px-md-4">Save And Update</button>
                    </div>
                </div>
                @role('root|admin')
                </form>
            @endrole

            {{-- Tracks in this Album --}}
            <div class="card mt-4">
                <div class="card-header bg-dark py-2 d-flex justify-content-between align-items-center">
                    <h5 class="text-white mb-0">Tracks in this Album</h5>
                    <div style="gap: 6px" class="d-flex">
                        <a href="{{ route('playlist.create') }}?albam_id={{ $albam->id }}&content_type=audio"
                           class="btn btn-success btn-sm">
                            <i class="fa fa-music"></i> Add Music Track
                        </a>
                        <a href="{{ route('playlist.create') }}?albam_id={{ $albam->id }}&content_type=video"
                           class="btn btn-warning btn-sm">
                            <i class="fa fa-video-camera"></i> Add Video Track
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    @php $playlists = $albam->playlists; @endphp
                    @if ($playlists->isEmpty())
                        <p class="text-muted text-center py-3 mb-0">No tracks added to this album yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Thumbnail</th>
                                        <th>Name</th>
                                        <th>Duration</th>
                                        <th class="text-center">Type</th>
                                        <th class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($playlists as $track)
                                        <tr>
                                            <td>
                                                <img src="{{ $track->thumbnail }}" alt="" width="50" height="50" loading="lazy">
                                            </td>
                                            <td>{{ $track->name }}</td>
                                            <td>{{ $track->duration }}</td>
                                            <td class="text-center">
                                                @if (($track->content_type ?? 'audio') === 'video')
                                                    <span class="badge badge-warning">Video</span>
                                                @else
                                                    <span class="badge badge-success">Music</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <a href="{{ route('playlist.edit', $track->id) }}"
                                                   class="btn btn-info btn-sm" data-toggle="tooltip" title="Edit">
                                                    <i class="fa fa-edit"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
@push('scripts')
    <script>
        var previewLogoFile = function(event) {
            var reader = new FileReader();
            reader.onload = function() {
                var output = document.getElementById('logoPreview');
                output.src = reader.result;
            };
            reader.readAsDataURL(event.target.files[0]);
        };
    </script>
@endpush
