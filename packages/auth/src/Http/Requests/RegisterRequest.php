<?php

namespace Youandme\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Youandme\Auth\Rules\ValidNickname;

class RegisterRequest extends FormRequest
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
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'password' => ['required', 'string', 'min:8'],
            'nickname' => ['required', new ValidNickname],
        ];
    }
}
