<?php

namespace App\Http\Requests\Api\V1\Profile;

use App\Rules\ValidNickname;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
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
            'nickname' => ['sometimes', new ValidNickname($this->user()?->id)],
            'timezone' => ['sometimes', 'timezone'],
            'locale' => ['sometimes', 'string', 'size:2'],
        ];
    }
}
