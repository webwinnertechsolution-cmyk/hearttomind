@extends('layouts.app')

@section('content')
    <div class="container-fluid mt-5">
        <div class="row">
            <div class="col-lg-9 col-md-12 m-auto">
                <div class="card">
                    <div class="card-header bg-custom">
                        <h3 class="text-white m-0">{{ $setting->title }}</h3>
                    </div>
                    <div class="card-body">
                        {!! $setting->content !!}
                    </div>
                    <div class="card-footer text-right py-2 bg-white">
                        <a href="{{ route('setting.edit', $setting->slug) }}" class="btn btn-custom px-4">Edit</a>

                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
