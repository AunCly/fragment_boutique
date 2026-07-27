@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <div id="builder-root"></div>
@endsection

@push('head')
    <link rel="stylesheet" href="{{ asset('builder/assets/index.css') }}">
@endpush

@push('body')
    <script>
        window.FRAGMENT_WOOD_TEXTURES = @json($woodTextures);
        window.FRAGMENT_FORMATS = @json($formats);
    </script>
    <script type="module" src="{{ asset('builder/assets/index.js') }}"></script>
@endpush
