{{-- Form validation errors. Deliberately NOT auto-dismissed: they sit above the
     form they belong to and stay until it is resubmitted, because a frosted
     toast that fades would vanish while the author is still fixing the fields.
     Success and failure notifications travel separately, as floating toasts in
     layouts/app.blade.php. --}}
@if (isset($errors) && $errors->any())
    <div class="alert alert--error" role="alert">
        <p class="alert__title">Please correct the following:</p>
        <ul class="alert__list">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
