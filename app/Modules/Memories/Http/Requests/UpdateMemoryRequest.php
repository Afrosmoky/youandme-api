<?php

namespace App\Modules\Memories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Editing a memory replaces BOTH answers, even though the verb is PATCH: the edit
 * screen holds the whole card, so a missing answer_b means "the partner said
 * nothing", not "leave whatever was there". Same limits as the original save.
 *
 * Nothing else is editable — the question, the origin, the name snapshots and
 * answered_at are history, not content.
 */
class UpdateMemoryRequest extends FormRequest
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
            'answer_a' => ['required', 'string', 'max:5000'],
            'answer_b' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answer_a.required' => 'Odpowiedź jest wymagana.',
            'answer_a.max' => 'Odpowiedź jest za długa.',
            'answer_b.max' => 'Odpowiedź jest za długa.',
        ];
    }
}
