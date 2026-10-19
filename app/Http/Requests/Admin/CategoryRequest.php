<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryRequest extends FormRequest
{
    /**
     * The route group already requires an authenticated administrator.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Lengths mirror the schema: categories.name VARCHAR(100),
     * categories.slug VARCHAR(120), categories.description VARCHAR(500).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('categories', 'name')->ignore($category?->getKey()),
            ],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * The slug is derived from the name rather than typed by the admin, so the
     * two cannot disagree. Str::slug produces exactly the convention the seeded
     * categories already use ("Image Generation" -> "image-generation").
     *
     * Renaming a category therefore changes its slug, which is safe: prompts
     * reference categories by id, and nothing routes by slug.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'description' => $this->input('description'),
        ]);
    }

    /**
     * Validation that needs the derived slug, which the rules() method cannot
     * see because it runs before prepareForValidation().
     *
     * @return array<string, array<int, mixed>>
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $name = (string) $this->input('name');
            $slug = Str::slug($name);

            if ($name !== '' && $slug === '') {
                $validator->errors()->add('name', 'That name cannot be turned into a usable slug. Use letters or numbers.');

                return;
            }

            $category = $this->route('category');

            $taken = Category::query()
                ->where('slug', $slug)
                ->when($category, fn ($query) => $query->whereKeyNot($category->getKey()))
                ->exists();

            if ($taken) {
                $validator->errors()->add(
                    'name',
                    'Another category already uses the slug "'.$slug.'". Choose a different name.'
                );
            }
        });
    }

    /**
     * The slug that will be stored, derived from the validated name.
     */
    public function slug(): string
    {
        return Str::slug((string) $this->input('name'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['name' => 'category name'];
    }
}
