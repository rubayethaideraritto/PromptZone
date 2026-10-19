{{--
    Shared category fields for the create and edit forms.
    Expects: $category (Category|null)
--}}

<div class="field">
    <label class="field__label" for="name">Category name</label>
    <input class="field__input"
           id="name"
           name="name"
           type="text"
           value="{{ old('name', $category?->name) }}"
           required
           maxlength="100"
           @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
    <p class="field__hint">
        The URL slug is generated from the name automatically, so renaming a category
        also changes its slug. Prompts keep it either way, because they reference the
        category by id.
    </p>
    @error('name')
        <p class="field__error" id="name-error">{{ $message }}</p>
    @enderror
</div>

<div class="field">
    <label class="field__label" for="description">
        Description <span class="field__optional">(optional)</span>
    </label>
    <textarea class="field__input"
              id="description"
              name="description"
              rows="2"
              maxlength="500"
              @error('description') aria-invalid="true" aria-describedby="description-error" @enderror>{{ old('description', $category?->description) }}</textarea>
    @error('description')
        <p class="field__error" id="description-error">{{ $message }}</p>
    @enderror
</div>
