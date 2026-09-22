<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE /me. The body is optional: apple_authorization_code is sent for
 * accounts linked to Apple, from a fresh Sign in with Apple right before the
 * call, so our tokens can be revoked.
 */
final class DeleteAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'apple_authorization_code' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
