<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Validation only; the route is already behind the `auth` middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lengths mirror the schema: users.name VARCHAR(120), users.email VARCHAR(190).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => [
                'required',
                'string',
                'email',
                'max:190',
                // Unique across all accounts except this one, so saving the form
                // without changing the address does not collide with itself.
                Rule::unique('users', 'email')->ignore($this->user()->getKey()),
            ],
        ];
    }

    /**
     * Trim the name and lowercase the email, matching how registration stores it.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'email' => Str::lower(trim((string) $this->input('email'))),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['email' => 'email address'];
    }
}
