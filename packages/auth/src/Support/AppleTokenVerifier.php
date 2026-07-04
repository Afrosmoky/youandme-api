<?php

namespace Youandme\Auth\Support;

final class AppleTokenVerifier extends JwksTokenVerifier implements AppleTokenVerifierInterface
{
    protected function jwksUrl(): string
    {
        return 'https://appleid.apple.com/auth/keys';
    }

    /** @return list<string> */
    protected function allowedIssuers(): array
    {
        return ['https://appleid.apple.com'];
    }

    protected function audience(): ?string
    {
        return config('services.apple.client_id');
    }

    /**
     * Apple does not include the user's name in the ID token (it is sent once,
     * separately, on first authorization), so name is always null here.
     *
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     */
    protected function mapClaims(object $claims): array
    {
        return [
            'sub' => (string) $claims->sub,
            'email' => isset($claims->email) ? (string) $claims->email : null,
            'email_verified' => filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL),
            'name' => null,
        ];
    }
}
