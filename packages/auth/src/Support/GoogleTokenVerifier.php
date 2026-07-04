<?php

namespace Youandme\Auth\Support;

final class GoogleTokenVerifier extends JwksTokenVerifier implements GoogleTokenVerifierInterface
{
    protected function jwksUrl(): string
    {
        return 'https://www.googleapis.com/oauth2/v3/certs';
    }

    /** @return list<string> */
    protected function allowedIssuers(): array
    {
        return ['accounts.google.com', 'https://accounts.google.com'];
    }

    protected function audience(): ?string
    {
        return config('services.google.client_id');
    }

    /**
     * @return array{sub: string, email: ?string, email_verified: bool, name: ?string}
     */
    protected function mapClaims(object $claims): array
    {
        return [
            'sub' => (string) $claims->sub,
            'email' => isset($claims->email) ? (string) $claims->email : null,
            'email_verified' => filter_var($claims->email_verified ?? false, FILTER_VALIDATE_BOOL),
            'name' => isset($claims->name) ? (string) $claims->name : null,
        ];
    }
}
