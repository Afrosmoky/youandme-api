<?php

use Illuminate\Support\Facades\Log;
use Youandme\Auth\Actions\RevokeAppleTokensAction;
use Youandme\Auth\Models\User;
use Youandme\Auth\Support\AppleAuthClientInterface;
use Youandme\Auth\Support\AppleAuthException;
use Youandme\Auth\Support\AppleTokenGrant;
use Youandme\Auth\Support\AppleTokenVerifierInterface;
use Youandme\Auth\Support\SocialTokenException;

function appleClaims(string $sub): array
{
    return ['sub' => $sub, 'email' => null, 'email_verified' => false, 'name' => null];
}

/** Nothing secret or identifying may reach a log line. */
function assertLogsNothingSensitive(string ...$needles): void
{
    Log::shouldHaveReceived('warning')->withArgs(function (string $message, array $context = []) use ($needles): bool {
        $line = $message.json_encode($context);
        foreach ($needles as $needle) {
            if (str_contains($line, $needle)) {
                return false;
            }
        }

        return true;
    })->once();
}

beforeEach(function (): void {
    Log::spy();
    $this->user = User::factory()->create(['apple_id' => 'apple-sub-1']);
    $this->client = $this->mock(AppleAuthClientInterface::class);
    $this->verifier = $this->mock(AppleTokenVerifierInterface::class);
});

test('the matching Apple ID gets its refresh token revoked', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnTrue();
    $this->client->shouldReceive('exchangeAuthorizationCode')->with('the-code')->once()->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    $this->verifier->shouldReceive('verify')->with('id-token')->andReturn(appleClaims('apple-sub-1'));
    $this->client->shouldReceive('revokeRefreshToken')->with('r-token')->once();

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeTrue();
    Log::shouldNotHaveReceived('warning');
});

test('a code for a different Apple ID revokes nothing and logs no identifier', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnTrue();
    $this->client->shouldReceive('exchangeAuthorizationCode')->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    $this->verifier->shouldReceive('verify')->andReturn(appleClaims('someone-else'));
    $this->client->shouldNotReceive('revokeRefreshToken');

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeFalse();
    assertLogsNothingSensitive('someone-else', 'apple-sub-1', $this->user->ulid, $this->user->email, 'the-code', 'r-token', 'id-token');
});

test('an ID token that does not verify revokes nothing', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnTrue();
    $this->client->shouldReceive('exchangeAuthorizationCode')->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    $this->verifier->shouldReceive('verify')->andThrow(new SocialTokenException('Invalid ID token: expired'));
    $this->client->shouldNotReceive('revokeRefreshToken');

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeFalse();
    assertLogsNothingSensitive('the-code', 'r-token', 'id-token');
});

test('no code is skipped with a warning', function (): void {
    $this->client->shouldNotReceive('exchangeAuthorizationCode');

    expect(RevokeAppleTokensAction::run($this->user, null))->toBeFalse();
    assertLogsNothingSensitive('apple-sub-1');
});

test('a missing key is skipped with a warning', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnFalse();
    $this->client->shouldNotReceive('exchangeAuthorizationCode');

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeFalse();
    assertLogsNothingSensitive('the-code');
});

test('a failing exchange is logged without the code', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnTrue();
    $this->client->shouldReceive('exchangeAuthorizationCode')->andThrow(new AppleAuthException('Apple /auth/token failed with HTTP 400 (invalid_grant).'));

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeFalse();
    assertLogsNothingSensitive('the-code');
});

test('a failing revocation is logged without the tokens', function (): void {
    $this->client->shouldReceive('isConfigured')->andReturnTrue();
    $this->client->shouldReceive('exchangeAuthorizationCode')->andReturn(new AppleTokenGrant('r-token', 'id-token'));
    $this->verifier->shouldReceive('verify')->andReturn(appleClaims('apple-sub-1'));
    $this->client->shouldReceive('revokeRefreshToken')->andThrow(new AppleAuthException('Apple /auth/revoke failed with HTTP 500.'));

    expect(RevokeAppleTokensAction::run($this->user, 'the-code'))->toBeFalse();
    assertLogsNothingSensitive('the-code', 'r-token');
});

test('an account not linked to Apple has nothing to revoke and nothing to log', function (): void {
    $user = User::factory()->create();
    $this->client->shouldNotReceive('exchangeAuthorizationCode');

    expect(RevokeAppleTokensAction::run($user, 'the-code'))->toBeFalse();
    Log::shouldNotHaveReceived('warning');
});
