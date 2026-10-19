@extends('layouts.app')

@section('title', 'Create prompt')

@section('content')
    <div class="page-head">
        <h1>Create prompt</h1>
        <p class="page-head__sub">
            Prompts are saved as <strong>private</strong> unless you choose to publish them.
        </p>
    </div>

    <form method="POST" action="{{ route('prompts.store') }}" class="panel form">
        @csrf

        @include('prompts.partials.form', ['prompt' => null, 'tagString' => ''])

        <div class="form__actions">
            <button type="submit" class="btn btn--primary">Save prompt</button>
            <a class="btn" href="{{ route('dashboard') }}">Cancel</a>
        </div>
    </form>
@endsection
