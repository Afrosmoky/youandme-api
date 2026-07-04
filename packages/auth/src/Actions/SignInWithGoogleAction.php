<?php

namespace Youandme\Auth\Actions;

use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Actions\Concerns\ResolvesSocialSignIn;
use Youandme\Auth\Data\AuthResult;
use Youandme\Auth\Support\GoogleTokenVerifierInterface;

class SignInWithGoogleAction
{
    use AsAction, ResolvesSocialSignIn;

    public function __construct(
        private readonly GoogleTokenVerifierInterface $verifier,
    ) {}

    public function handle(string $idToken): AuthResult
    {
        return $this->signIn($this->verifier, $idToken, 'google_id');
    }
}
