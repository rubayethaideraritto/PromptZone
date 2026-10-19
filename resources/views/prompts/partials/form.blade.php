{{--
    Shared create/edit fields.
    Expects: $prompt (Prompt|null), $categories, $tagString (string|null)
--}}

<div class="field">
    <label class="field__label" for="title">Title</label>
    <input class="field__input"
           id="title"
           name="title"
           type="text"
           value="{{ old('title', $prompt?->title) }}"
           required
           maxlength="{{ config('prompts.max_title_length') }}"
           @error('title') aria-invalid="true" aria-describedby="title-error" @enderror>
    @error('title')
        <p class="field__error" id="title-error">{{ $message }}</p>
    @enderror
</div>

<div class="field">
    <label class="field__label" for="description">Description <span class="field__optional">(optional)</span></label>
    <textarea class="field__input"
              id="description"
              name="description"
              rows="2"
              maxlength="{{ config('prompts.max_description_length') }}"
              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $prompt?->description) }}</textarea>
    @error('description')
        <p class="field__error" id="description-error">{{ $message }}</p>
    @enderror
</div>

<div class="field">
    <label class="field__label" for="prompt_text">Prompt text</label>
    <textarea class="field__input field__input--code"
              id="prompt_text"
              name="prompt_text"
              rows="10"
              required
              maxlength="{{ config('prompts.max_prompt_text_length') }}"
              @error('prompt_text') aria-invalid="true" aria-describedby="prompt_text-error" @enderror>{{ old('prompt_text', $prompt?->prompt_text) }}</textarea>
    @error('prompt_text')
        <p class="field__error" id="prompt_text-error">{{ $message }}</p>
    @enderror
</div>

{{-- Possible-duplicate warning.
     It sits directly under the field it reacts to, not at the top of the form:
     a warning that renders 270px above the box being typed into is off screen on
     a form this tall, and therefore never seen. Filled in by public/js/app.js.
     Advisory only: the form always submits. --}}
<div class="duplicate-check"
     data-duplicate-check
     data-duplicate-url="{{ route('prompts.duplicate-check') }}"
     @isset($prompt) data-duplicate-ignore="{{ $prompt->getKey() }}" @endisset
     hidden></div>

{{-- The AI panel also acts on this field, so it stays adjacent too. --}}
@include('prompts.partials.ai-panel', ['aiConfigured' => $aiConfigured ?? false, 'prompt' => $prompt ?? null])

<div class="field-row">
    <div class="field">
        <label class="field__label" for="category_id">Category</label>
        <select class="field__input"
                id="category_id"
                name="category_id"
                required
                @error('category_id') aria-invalid="true" aria-describedby="category_id-error" @enderror>
            <option value="">Choose a category&hellip;</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}"
                    @selected((int) old('category_id', $prompt?->category_id) === $category->id)>
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        @error('category_id')
            <p class="field__error" id="category_id-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="field">
        <label class="field__label" for="ai_model">AI model</label>
        <select class="field__input" id="ai_model" name="ai_model" required>
            @foreach (config('prompts.ai_models') as $model)
                <option value="{{ $model }}" @selected(old('ai_model', $prompt?->ai_model ?? 'any') === $model)>
                    {{ $model }}
                </option>
            @endforeach
        </select>
        @error('ai_model')
            <p class="field__error" id="ai_model-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="field">
        <label class="field__label" for="language">Language</label>
        <select class="field__input" id="language" name="language" required>
            @foreach (config('prompts.languages') as $language)
                <option value="{{ $language }}" @selected(old('language', $prompt?->language ?? 'English') === $language)>
                    {{ $language }}
                </option>
            @endforeach
        </select>
        @error('language')
            <p class="field__error" id="language-error">{{ $message }}</p>
        @enderror
    </div>

    <div class="field">
        <label class="field__label" for="visibility">Visibility</label>
        <select class="field__input" id="visibility" name="visibility" required>
            @foreach (config('prompts.visibilities') as $visibility)
                <option value="{{ $visibility }}" @selected(old('visibility', $prompt?->visibility ?? 'private') === $visibility)>
                    {{ ucfirst($visibility) }}
                </option>
            @endforeach
        </select>
        <p class="field__hint">Private prompts are visible only to you.</p>
        @error('visibility')
            <p class="field__error" id="visibility-error">{{ $message }}</p>
        @enderror
    </div>
</div>

<div class="field">
    <label class="field__label" for="tags">Tags <span class="field__optional">(optional)</span></label>
    <input class="field__input"
           id="tags"
           name="tags"
           type="text"
           value="{{ old('tags', $tagString ?? '') }}"
           placeholder="laravel, debugging, step-by-step"
           maxlength="{{ config('prompts.max_tag_input_length') }}"
           @error('tags') aria-invalid="true" aria-describedby="tags-error" @enderror>
    <p class="field__hint">Separate tags with commas. Existing tags are reused.</p>
    @error('tags')
        <p class="field__error" id="tags-error">{{ $message }}</p>
    @enderror
</div>
