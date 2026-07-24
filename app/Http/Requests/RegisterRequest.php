<?php

namespace App\Http\Requests;

use Closure;
use Youandme\Auth\Http\Requests\RegisterRequest as PackageRegisterRequest;
use Youandme\Auth\Queries\GetUserIdByNicknameQuery;

/**
 * App-layer registration request: the reusable Auth package validates email /
 * password / nickname; the product-specific referrer_nickname rule is added here
 * so the package stays free of the referral (growth) mechanic (canon §4).
 *
 * referrer_nickname is optional, but when given it must point at an existing user
 * and must not be the registrant's own nickname (self-referral). Both failures
 * surface on the referrer_nickname field (422), so the client can fix or clear it.
 */
final class RegisterRequest extends PackageRegisterRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'referrer_nickname' => ['nullable', 'string', $this->validReferrer()],
        ];
    }

    private function validReferrer(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $ownNickname = $this->input('nickname');
            if (is_string($ownNickname) && mb_strtolower($value) === mb_strtolower($ownNickname)) {
                $fail('Nie możesz polecić samego siebie.');

                return;
            }

            if (GetUserIdByNicknameQuery::run($value) === null) {
                $fail('Nie znaleziono użytkownika o tym nicku.');
            }
        };
    }
}
