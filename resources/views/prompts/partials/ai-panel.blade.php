{{--
    AI assistance for the create and edit forms.

    Expects:
      $aiConfigured  (bool)  whether a provider key is present
      $prompt        (Prompt|null)  present when editing, so a version can be saved

    Every result is rendered from the provider's reply. Nothing is fabricated in
    the view, and nothing is applied to the form without the user pressing a
    button: the panel never writes into the category, tags or prompt text fields
    on its own.
--}}
@if ($aiConfigured)
    <section class="ai-panel"
             data-ai-panel
             data-analyze-url="{{ route('ai.analyze') }}"
             data-improve-url="{{ route('ai.improve') }}"
             data-suggest-url="{{ route('ai.suggest') }}"
             @isset($prompt) data-version-url="{{ route('prompts.versions.store', $prompt) }}" @endisset>
        <div class="ai-panel__head">
            <h3 class="ai-panel__title">
                <span aria-hidden="true">&#10024;</span> AI assistance
            </h3>
            <p class="ai-panel__note">
                Sends the text currently in the <strong>Prompt text</strong> box to
                <code>{{ config('services.gemini.model') }}</code>. AI output can be wrong:
                review it before you use it. Nothing is saved until you save the form.
            </p>
        </div>

        <div class="ai-panel__actions">
            {{-- The gradient and the sparkle come from .btn--ai in app.css, so
                 the buttons stay plain in markup and public/js/app.js keeps
                 reading textContent without picking up an icon span. --}}
            <button type="button" class="btn btn--sm btn--ai" data-ai-action="analyze">
                Analyze
            </button>
            <button type="button" class="btn btn--sm btn--ai" data-ai-action="improve">
                Improve
            </button>
            <button type="button" class="btn btn--sm btn--ai" data-ai-action="suggest">
                Suggest category &amp; tags
            </button>
        </div>

        {{-- Loading, success and failure all report here. --}}
        <p class="ai-panel__status" data-ai-status role="status" aria-live="polite"></p>

        <div class="ai-panel__result" data-ai-result hidden></div>
    </section>
@else
    {{-- Shown instead of buttons that could not work. The rest of the form and
         the whole application remain usable. --}}
    <section class="ai-panel ai-panel--off">
        <h3 class="ai-panel__title">AI assistance is not configured</h3>
        <p class="ai-panel__note">
            Add a <code>GEMINI_API_KEY</code> to your <code>.env</code> file to enable the
            analyzer, the improver and the category and tag suggestions. Everything else works
            without it.
        </p>
    </section>
@endif
