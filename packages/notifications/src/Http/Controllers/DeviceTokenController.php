<?php

namespace Youandme\Notifications\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Http\Requests\RegisterDeviceTokenRequest;

/**
 * POST /device-tokens — "push me here". Called after login and whenever FCM
 * rotates the client's registration token.
 *
 * The push channel owns its own addressing, so the endpoint belongs to the
 * package (canon §1 row 11). The only thing it borrows from the host application
 * is the authenticated identity, and it borrows it as a VALUE: the caller's ulid,
 * never a user model, a couple, or anything domain-shaped. Notifications still
 * knows nothing about "Ja i Ty".
 *
 * The user comes from the token, never from the payload — a client-supplied user
 * would let anyone point somebody else's notifications at their own phone.
 */
final class DeviceTokenController
{
    public function store(RegisterDeviceTokenRequest $request): JsonResponse
    {
        /** @var string $token */
        $token = $request->validated('token');
        /** @var string $platform */
        $platform = $request->validated('platform');

        RegisterDeviceTokenAction::run($request->user()->ulid, $token, $platform);

        return response()->json(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
