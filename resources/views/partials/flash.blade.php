{{--
    Floating, self-dismissing notifications.

    Rendered at the end of the body inside a fixed container so they never leave a
    gap in the page flow, and styled to fade away. The fade is a CSS animation
    gated on prefers-reduced-motion, so no JavaScript is involved: with motion
    reduced the message simply stays on screen instead, which is the safer
    behaviour for anyone sensitive to movement.

    Both are pointer-events: none, so even a toast still on screen cannot block a
    click on the content underneath.
--}}
@if (session('status'))
    <div class="toast toast--success" role="status" aria-live="polite">
        {{ session('status') }}
    </div>
@endif

@if (session('error'))
    <div class="toast toast--error" role="alert" aria-live="assertive">
        {{ session('error') }}
    </div>
@endif
