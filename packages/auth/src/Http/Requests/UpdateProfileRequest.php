<?php

namespace Youandme\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Youandme\Auth\Rules\ValidNickname;

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
            // partner_name_local is a Game (Couple) field, orchestrated by the
            // controller. TODO Etap 4 (Game): validate via Game module.
            'partner_name_local' => ['sometimes', 'nullable', 'string', 'max:60'],
        ];
    }
}
