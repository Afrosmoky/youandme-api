<?php

namespace App\Modules\Memories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocalMemoryRequest extends FormRequest
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
        // player_b_name is required and comes from the request, unlike every other
        // save: in local play the second player is a name typed on the setup
        // screen, not the couple's stored partner_name_local, and the two may
        // legitimately differ. Capped at 60 like partner_name_local, because they
        // land in the same column shape and a couple would rightly expect the two
        // fields to accept the same thing.
        return [
            'question_ulid' => ['required', 'string', 'exists:questions,ulid'],
            'answer_a' => ['required', 'string', 'max:5000'],
            'answer_b' => ['nullable', 'string', 'max:5000'],
            'player_b_name' => ['required', 'string', 'max:60'],
            // Backdated yes, post-dated no — the same rule and the same five
            // minutes of clock-drift tolerance as the session save. Local play is
            // exactly the case that needs backdating (the phone may sync hours
            // later), which is why the ceiling sits just ahead of now rather than
            // demanding a live request.
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
            'answer_b.max' => 'Odpowiedź jest za długa.',
            'player_b_name.required' => 'Imię drugiego gracza jest wymagane.',
            'player_b_name.max' => 'Imię drugiego gracza jest za długie.',
            'answered_at.required' => 'Data odpowiedzi jest wymagana.',
            'answered_at.date' => 'Data odpowiedzi ma nieprawidłowy format.',
            'answered_at.before_or_equal' => 'Data odpowiedzi nie może być z przyszłości.',
        ];
    }
}
