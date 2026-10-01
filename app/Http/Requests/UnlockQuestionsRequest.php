<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnlockQuestionsRequest extends FormRequest
{
    /**
     * Cards per request. The closed deck has 40 today; 50 leaves room for it to
     * grow without a release, and still bounds the single INSERT.
     */
    public const MAX_CARDS = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Whether each ulid is a real session card is checked by the controller in
        // one query — `exists` here would be one query per card.
        return [
            'question_ulids' => ['required', 'array', 'list', 'min:1', 'max:'.self::MAX_CARDS],
            'question_ulids.*' => ['required', 'string', 'size:26'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'question_ulids.required' => 'Wybierzcie karty do odblokowania.',
            'question_ulids.array' => 'Lista kart ma nieprawidłowy format.',
            'question_ulids.list' => 'Lista kart ma nieprawidłowy format.',
            'question_ulids.min' => 'Wybierzcie karty do odblokowania.',
            'question_ulids.max' => 'Jednym razem można odblokować najwyżej '.self::MAX_CARDS.' kart.',
            'question_ulids.*.required' => 'Nieprawidłowy identyfikator karty.',
            'question_ulids.*.string' => 'Nieprawidłowy identyfikator karty.',
            'question_ulids.*.size' => 'Nieprawidłowy identyfikator karty.',
        ];
    }

    /**
     * The requested ulids, de-duplicated in request order. Duplicates are merged
     * rather than refused: they could never be charged twice anyway (the charge
     * follows the inserted rows), so refusing would only punish a sloppy client.
     *
     * @return list<string>
     */
    public function questionUlids(): array
    {
        /** @var list<string> $ulids */
        $ulids = $this->validated('question_ulids');

        return array_values(array_unique($ulids));
    }
}
