@extends('layouts.app')

@section('title', '403 — Forbidden')

@section('content')
    <div class="empty-state">
        <h1>403 &mdash; Forbidden</h1>
        <p>Your account does not have permission to open this page.</p>
        <p class="empty-state__actions">
            <a class="btn btn--primary" href="{{ url('/library') }}">Back to the library</a>
        </p>
    </div>
@endsection
