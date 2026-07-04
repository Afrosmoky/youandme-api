<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Actions\Concerns\ResolvesSocialSignIn;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Support\AppleTokenVerifierInterface;

final class SignInWithAppleAction
{
    use AsAction, ResolvesSocialSignIn;

    public function __construct(
        private readonly AppleTokenVerifierInterface $verifier,
    ) {}

    public function handle(string $idToken): AuthResult
    {
        return $this->signIn($this->verifier, $idToken, 'apple_id');
    }
}
