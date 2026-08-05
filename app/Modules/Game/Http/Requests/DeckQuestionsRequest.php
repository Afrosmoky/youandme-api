<?php

namespace App\Modules\Game\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DeckQuestionsRequest extends FormRequest
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
        // No category = mix mode, the same convention as POST /sessions/start.
        // An unknown slug is a 422 rather than an empty deck, also as there: a
        // client asking for a category that does not exist has a bug, and an
        // empty list would hide it behind something that looks like "we ran out".
        //
        // limit is clamped rather than rejected (see the controller) — a number
        // out of range is a preference we can satisfy approximately, not a
        // mistake about what exists.
        return [
            'category' => ['nullable', 'string', 'exists:categories,slug'],
            'limit' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category.exists' => 'Wskazana kategoria nie istnieje.',
            'limit.integer' => 'Liczba kart ma nieprawidłowy format.',
        ];
    }
}
