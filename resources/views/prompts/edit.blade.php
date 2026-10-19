@extends('layouts.app')

@section('title', 'Edit prompt')

@section('content')
    <div class="page-head">
        <h1>Edit prompt</h1>
        <p class="page-head__sub">{{ $prompt->title }}</p>
    </div>

    <form method="POST" action="{{ route('prompts.update', $prompt) }}" class="panel form">
        @csrf
        @method('PUT')

        @include('prompts.partials.form', ['prompt' => $prompt, 'tagString' => $tagString])

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">Save changes</button>
            <a class="btn" href="{{ route('prompts.show', $prompt) }}">Cancel</a>
        </div>
    </form>
@endsection
