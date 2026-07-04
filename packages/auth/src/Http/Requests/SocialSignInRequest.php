<?php

namespace Youandme\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SocialSignInRequest extends FormRequest
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
            'id_token' => ['required', 'string'],
        ];
    }
}
