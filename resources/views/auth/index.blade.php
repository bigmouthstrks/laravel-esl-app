@extends('layouts.app')
@section('main')

<div class="container">
    <h3>Welcome, {{ $user->name }}</h3>
</div>

@include('partials.prompt')

@endsection