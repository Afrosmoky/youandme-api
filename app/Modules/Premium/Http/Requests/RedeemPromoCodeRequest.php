<?php

namespace App\Modules\Premium\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input shape for POST /redeem. Lives in Premium even though the endpoint is
 * orchestrated by the app layer — the module owns what a code looks like, the
 * same split as Memories' StoreMemoryRequest.
 *
 * No couple in the payload, ever: it comes from the token (IDOR — canon §5).
 */
class RedeemPromoCodeRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64'],
        ];
    }
}
