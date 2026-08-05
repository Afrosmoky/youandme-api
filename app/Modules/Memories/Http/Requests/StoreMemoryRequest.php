<?php

namespace App\Modules\Memories\Http\Requests;

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
        // answered_at may be backdated but never meaningfully post-dated.
        // Backdating is normal — a client that played offline syncs later — while
        // a future date is either a broken clock or an attempt to plant an
        // anniversary (P9 scans answered_at).
        //
        // The ceiling is five minutes ahead, not "now": phone clocks drift, and a
        // couple must not lose an answer because their device runs a few seconds
        // fast. The window is far too small to move an anniversary and far too
        // large to be tripped by drift.
        return [
            'question_ulid' => ['required', 'string', 'exists:questions,ulid'],
            'answer_a' => ['required', 'string', 'max:5000'],
            'answer_b' => ['nullable', 'string', 'max:5000'],
            'answered_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
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
            'answered_at.before_or_equal' => 'Data odpowiedzi nie może być z przyszłości.',
        ];
    }
}
