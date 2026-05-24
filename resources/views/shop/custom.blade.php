@extends('layouts.shop')

@section('main-class', 'w-full')

@push('head')
    <link rel="stylesheet" href="{{ asset('builder/assets/index.css') }}">
@endpush

@section('content')
    <div id="builder-root"></div>
@endsection

@push('body')
    <script type="module" src="{{ asset('builder/assets/index.js') }}"></script>
@endpush
