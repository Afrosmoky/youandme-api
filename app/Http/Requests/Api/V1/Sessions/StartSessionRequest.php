<?php

namespace App\Http\Requests\Api\V1\Sessions;

use Illuminate\Foundation\Http\FormRequest;

class StartSessionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // null category_slug = mix mode (questions from all categories).
        return [
            'category_slug' => ['nullable', 'string', 'exists:categories,slug'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_slug.exists' => 'Wybrana kategoria nie istnieje.',
        ];
    }
}
