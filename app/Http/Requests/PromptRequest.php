<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PromptRequest extends FormRequest
{
    /**
     * Validation only. Authorisation is enforced by PromptPolicy in the
     * controller via $this->authorize(), because it depends on the bound model.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lengths mirror the manual schema. The controlled vocabularies come from
     * config/prompts.php so this rule set and the form dropdowns cannot drift.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.config('prompts.max_title_length')],
            'description' => ['nullable', 'string', 'max:'.config('prompts.max_description_length')],
            'prompt_text' => ['required', 'string', 'max:'.config('prompts.max_prompt_text_length')],

            // A prompt must belong to a category (prompts.category_id is NOT NULL).
            'category_id' => ['required', 'integer', 'exists:categories,id'],

            'ai_model' => ['required', 'string', Rule::in(config('prompts.ai_models'))],
            'language' => ['required', 'string', Rule::in(config('prompts.languages'))],
            'visibility' => ['required', 'string', Rule::in(config('prompts.visibilities'))],

            'tags' => ['nullable', 'string', 'max:'.config('prompts.max_tag_input_length')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prompt_text' => 'prompt text',
            'category_id' => 'category',
            'ai_model' => 'AI model',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ai_model.in' => 'Choose one of the supported AI models.',
            'language.in' => 'Choose one of the supported languages.',
            'visibility.in' => 'Visibility must be either private or public.',
        ];
    }
}
