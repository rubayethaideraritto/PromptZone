<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DuplicateCheckRequest extends FormRequest
{
    /**
     * The route group already requires an authenticated user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Either field may be missing while the author is still typing, so both are
     * optional; the service returns nothing when there is too little text to
     * compare.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:'.config('prompts.max_title_length')],
            'prompt_text' => ['nullable', 'string', 'max:'.config('prompts.max_prompt_text_length')],
            // Excluded from the comparison when editing, so a prompt does not
            // match itself. Only the author's own prompts are ever compared, so
            // an id belonging to somebody else simply has no effect.
            'ignore_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
