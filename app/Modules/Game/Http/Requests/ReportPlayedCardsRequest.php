<?php

namespace App\Modules\Game\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportPlayedCardsRequest extends FormRequest
{
    /**
     * Upper bound on a single report. A local session is a few dozen cards and
     * the whole Polish session deck is 100, so this is generous rather than
     * tight: it bounds the statement, it does not police the client. A longer
     * stretch of play splits into two reports at no cost — the write is a set,
     * so the split is invisible in the result.
     */
    private const MAX_CARDS = 100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Duplicates are rejected rather than quietly collapsed. The write would
        // survive them (set semantics), but a client sending the same card twice
        // in one batch has a sequencing bug, and MVP is when that is worth
        // hearing about.
        return [
            'question_ulids' => ['required', 'array', 'min:1', 'max:'.self::MAX_CARDS],
            'question_ulids.*' => ['required', 'string', 'distinct', 'exists:questions,ulid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_ulids.required' => 'Lista zagranych kart jest wymagana.',
            'question_ulids.array' => 'Lista zagranych kart ma nieprawidłowy format.',
            'question_ulids.min' => 'Lista zagranych kart jest pusta.',
            'question_ulids.max' => 'Lista zagranych kart jest za długa.',
            'question_ulids.*.required' => 'Wskazane pytanie jest wymagane.',
            'question_ulids.*.distinct' => 'Lista zagranych kart zawiera duplikaty.',
            'question_ulids.*.exists' => 'Wskazane pytanie nie istnieje.',
        ];
    }
}
