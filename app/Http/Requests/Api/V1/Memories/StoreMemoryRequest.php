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
            'answer' => ['required', 'string', 'max:5000'],
            'answered_at' => ['required', 'date'],
        ];
    }
}
