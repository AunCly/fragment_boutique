@extends('layouts.shop')

@section('main-class', 'w-full')

@section('content')
    <div id="builder-root"></div>
@endsection

@push('body')
    <script type="module" src="{{ asset('builder/assets/index.js') }}"></script>
@endpush
