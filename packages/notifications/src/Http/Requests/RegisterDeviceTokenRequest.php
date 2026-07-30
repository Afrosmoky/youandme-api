<?php

namespace Youandme\Notifications\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Input shape for registering a device. Lives in the package because the package
 * owns what an address looks like — the same split as Memories' StoreMemoryRequest
 * and Premium's RedeemPromoCodeRequest, even though the endpoint itself is
 * orchestrated by the app layer.
 *
 * No user in the payload, ever: it comes from the token (a client-supplied user
 * would redirect somebody else's notifications to this phone).
 *
 * iOS tokens are accepted and stored even though nothing is sent to them yet
 * (APNs is gated on the Apple account, T11) — refusing them would force a second
 * client release once it lights up.
 */
class RegisterDeviceTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ];
    }
}
