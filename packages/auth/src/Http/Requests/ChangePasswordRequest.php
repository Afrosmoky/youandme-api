<?php

namespace Youandme\Auth\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'different:current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_password.different' => 'Nowe hasło musi być inne niż obecne.',
        ];
    }
}
