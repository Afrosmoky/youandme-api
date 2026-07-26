<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\RegisterRequest;
use App\Modules\Game\Actions\CreateCoupleForUserAction;
use App\Modules\Game\Actions\RecordReferralAction;
use App\Modules\Game\Http\Resources\CoupleResource;
use App\Modules\Game\Models\Couple;
use App\Modules\Rewards\Actions\GrantCreditsAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Actions\LoginUserAction;
use Youandme\Auth\Actions\RegisterUserAction;
use Youandme\Auth\Actions\SetActiveCoupleForUserAction;
use Youandme\Auth\Data\RegisterUserInput;
use Youandme\Auth\Http\Requests\LoginRequest;
use Youandme\Auth\Http\Resources\UserResource;
use Youandme\Auth\Models\User;
use Youandme\Auth\Queries\GetUserIdByNicknameQuery;

/**
 * App composition root for the couple-returning auth endpoints: orchestrates the
 * pure Auth actions with Game (couple creation) in one transaction and composes
 * the {user, couple, token} response (byte-1:1 with P3). See §3.1.
 */
final class AuthController
{
    /** Symmetric referral bonus (canon §1 rows 7–8): the registrant gets it now. */
    private const REFERRAL_BONUS = 5;

    public function register(RegisterRequest $request): JsonResponse
    {
        [$user, $couple, $token] = DB::transaction(function () use ($request): array {
            $result = RegisterUserAction::run(RegisterUserInput::from($request->validated()));

            $user = User::where('ulid', $result->user->ulid)->firstOrFail();
            $couple = CreateCoupleForUserAction::run($user->id);
            SetActiveCoupleForUserAction::run($user, $couple->id);

            // Referral (product-specific, Game): the nickname is already validated
            // (exists, not self). Record the relation and pay the registrant's half
            // of the symmetric bonus now; the referrer's half waits for their first
            // app open (AwardReferralOnFirstOpen middleware).
            $referrerNickname = $request->input('referrer_nickname');
            if (is_string($referrerNickname) && $referrerNickname !== '') {
                $referrerId = GetUserIdByNicknameQuery::run($referrerNickname);
                if ($referrerId !== null) {
                    RecordReferralAction::run($referrerId, $user->id);
                    GrantCreditsAction::run($couple->id, self::REFERRAL_BONUS);
                }
            }

            return [$user, $couple, $result->token];
        });

        return response()->json([
            'user' => new UserResource($user),
            'couple' => new CoupleResource($couple),
            'token' => $token,
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $result = LoginUserAction::run(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        $user = User::where('ulid', $result->user->ulid)->firstOrFail();
        $couple = $user->active_couple_id !== null ? Couple::find($user->active_couple_id) : null;

        return response()->json([
            'user' => new UserResource($user),
            'couple' => $couple ? new CoupleResource($couple) : null,
            'token' => $result->token,
        ]);
    }
}
