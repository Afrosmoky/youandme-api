<?php

namespace App\Http\Requests\Api\V1\Memories;

use Illuminate\Foundation\Http\FormRequest;

class StoreMemoryRequest extends FormRequest
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
        return [
            'question_ulid' => ['required', 'string', 'exists:questions,ulid'],
            'answer_a' => ['required', 'string', 'max:5000'],
            'answer_b' => ['nullable', 'string', 'max:5000'],
            'answered_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_ulid.required' => 'Pytanie jest wymagane.',
            'question_ulid.exists' => 'Wskazane pytanie nie istnieje.',
            'answer_a.required' => 'Odpowiedź jest wymagana.',
            'answer_a.max' => 'Odpowiedź jest za długa.',
            'answered_at.required' => 'Data odpowiedzi jest wymagana.',
            'answered_at.date' => 'Data odpowiedzi ma nieprawidłowy format.',
        ];
    }
}
