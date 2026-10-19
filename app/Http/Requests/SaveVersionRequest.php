<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveVersionRequest extends FormRequest
{
    /**
     * Ownership is enforced by PromptPolicy in the controller, because it depends
     * on the bound prompt.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * `change_note` mirrors prompt_versions.change_note VARCHAR(500).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt_text' => ['required', 'string', 'max:'.config('prompts.max_prompt_text_length')],
            'change_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'prompt_text' => 'prompt text',
            'change_note' => 'change note',
        ];
    }
}
