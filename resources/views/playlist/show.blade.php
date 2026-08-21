@extends('layouts.app')

@push('title')
    <div class="page-title-box">
        <h4 class="page-title">Playlist Details</h4>
        <ol class="breadcrumb">
            <li class="breadcrumb-item active">playlist details view</li>
        </ol>
    </div>
@endpush
<style>
    th,
    td {
        padding: 16px 10px !important;
    }
</style>

@section('content')

<div class="row">
    <div class="col-12 col-lg-7 m-auto">
        <div class="card rounded-8">
            <div class="card-header py-2 bg-custom">
                <h4 class="text-white">Playlist Details</h4>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center" style="gap: 16px">
                    <img src="{{ $playlist->thumbnail }}" class="rounded-circle"  width="150" height="150">
                    <div>
                        <label class="text-muted mb-1">Name</label>
                        <p>{{ $playlist->name }}</p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-lg-6 mt-3">
                        <label class="text-muted mb-1 d-block">
                            Type:
                            <span class="badge badge-{{ ($playlist->content_type ?? 'audio') === 'video' ? 'warning' : 'success' }}">
                                {{ ($playlist->content_type ?? 'audio') === 'video' ? 'Video Track' : 'Music Track' }}
                            </span>
                        </label>
                        @if(($playlist->content_type ?? 'audio') === 'video')
                            @if($playlist->videoFile)
                                <video controls style="max-width:100%;border-radius:8px" src="{{ $playlist->videoFile }}"></video>
                            @else
                                <p class="text-muted">No video file uploaded.</p>
                            @endif
                        @else
                            <audio controls src="{{ $playlist->audioFile }}"></audio>
                        @endif
                    </div>
                    <div class="col-lg-6 mt-3">
                        <label class="text-muted mb-1">Duration</label>
                        <p>{{ $playlist->duration }}</p>
                    </div>
                    <div class="col-12 mt-3">
                        <label class="text-muted mb-1">Description</label>
                        <p>{{ $playlist->description }}</p>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white">
                <a href="{{ route('playlist.index') }}" type="button" class="btn btn-light btn">GO Back</a>
            </div>
        </div>
    </div>
</div>

@endsection
