<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AiRequest extends FormRequest
{
    /**
     * The route group already requires an authenticated user.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only text the signed-in user has explicitly submitted is ever sent to the
     * provider. Nothing is looked up by id, so an AI action can never be aimed
     * at another account's prompt.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'prompt_text' => ['required', 'string', 'max:'.config('prompts.max_prompt_text_length')],
            'purpose' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prompt_text.required' => 'Write the prompt text first, then run an AI action on it.',
            'prompt_text.max' => 'The prompt text is too long to send. Shorten it and try again.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['prompt_text' => 'prompt text'];
    }
}
