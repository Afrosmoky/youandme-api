<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use App\Modules\Rewards\Exceptions\AdMobKeysUnavailableException;
use App\Modules\Rewards\Models\AdRewardNonce;
use App\Modules\Rewards\Models\CoupleDailyAdReward;
use App\Modules\Rewards\Support\AdMobSignatureVerifierInterface;
use Mockery\MockInterface;

/*
 | GET /webhooks/admob-ssv — the only path from a watched ad to a credit from P7
 | on. The signature check is stubbed here (its own unit test drives the real
 | OpenSSL path); what these tests pin down is the trust logic around it: whose
 | couple gets paid, what a replay is worth, and when earning stops.
 */

function fakeSignatureCheck(bool $valid): void
{
    test()->mock(AdMobSignatureVerifierInterface::class, function (MockInterface $mock) use ($valid): void {
        $mock->shouldReceive('verify')->andReturn($valid);
    });
}

/** Build a callback shaped like AdMob's, with the signature last-but-one as Google sends it. */
function ssvCallback(string $nonce, string $signature = 'c2ln'): string
{
    $params = http_build_query([
        'ad_network' => '5450213213286189855',
        'ad_unit' => '1234567890',
        'custom_data' => $nonce,
        'reward_amount' => '1',
        'reward_item' => 'credits',
        'timestamp' => '1785456000000',
        'transaction_id' => '0123456789ABCDEF',
        'user_id' => 'client-supplied-and-ignored',
    ]);

    return '/api/v1/webhooks/admob-ssv?'.$params.'&signature='.$signature.'&key_id=1234567890';
}

test('a verified callback credits the couple that asked for the ad', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    $this->get(ssvCallback($nonce))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1)
        ->and(AdRewardNonce::query()->where('nonce', $nonce)->value('consumed_at'))->not->toBeNull();
});

test('the signed payload excludes the signature itself', function (): void {
    $user = createUserWithCouple();
    $nonce = IssueAdRewardNonceAction::run(activeCoupleOf($user)->id, $user->id);

    $this->mock(AdMobSignatureVerifierInterface::class, function (MockInterface $mock) use ($nonce): void {
        $mock->shouldReceive('verify')
            ->once()
            ->withArgs(function (string $signedData, string $signature, string $keyId) use ($nonce): bool {
                return ! str_contains($signedData, 'signature=')
                    && str_contains($signedData, 'custom_data='.$nonce)
                    && $signature === 'c2ln'
                    && $keyId === '1234567890';
            })
            ->andReturn(true);
    });

    $this->get(ssvCallback($nonce))->assertOk();
});

test('a replayed callback pays exactly once', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $callback = ssvCallback(IssueAdRewardNonceAction::run($coupleId, $user->id));

    $this->get($callback)->assertOk();
    $this->get($callback)->assertOk();
    $this->get($callback)->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('an unsigned or badly signed callback pays nothing', function (): void {
    fakeSignatureCheck(false);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    $this->get(ssvCallback($nonce))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(0)
        // The nonce survives a forgery: a genuine callback for it may still come.
        ->and(AdRewardNonce::query()->where('nonce', $nonce)->value('consumed_at'))->toBeNull();
});

test('a callback carrying an unknown nonce pays nothing', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();

    $this->get(ssvCallback('never-issued-by-us'))->assertOk();

    expect(creditsOfCouple(activeCoupleOf($user)->id))->toBe(0);
});

test('a malformed callback is answered, not crashed', function (): void {
    fakeSignatureCheck(true);
    createUserWithCouple();

    $this->get('/api/v1/webhooks/admob-ssv')->assertOk();
    $this->get('/api/v1/webhooks/admob-ssv?custom_data=x&reward_amount=1')->assertOk();
});

test('a nonce issued for one couple never credits another', function (): void {
    fakeSignatureCheck(true);
    $owner = createUserWithCouple();
    $stranger = createUserWithCouple();
    $ownerCoupleId = activeCoupleOf($owner)->id;
    $strangerCoupleId = activeCoupleOf($stranger)->id;

    $this->get(ssvCallback(IssueAdRewardNonceAction::run($ownerCoupleId, $owner->id)))->assertOk();

    expect(creditsOfCouple($ownerCoupleId))->toBe(1)
        ->and(creditsOfCouple($strangerCoupleId))->toBe(0);
});

test('the daily cap is enforced on the server, not by the client', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    foreach (range(1, 6) as $ignored) {
        $this->get(ssvCallback(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();
    }

    expect(creditsOfCouple($coupleId))->toBe(5)
        ->and(CoupleDailyAdReward::query()->where('couple_id', $coupleId)->value('count'))->toBe(5);
});

test('a couple that owns the whole closed deck stops earning from ads', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    $locked = Question::factory()->locked()->create();
    UnlockQuestionForCoupleAction::run($coupleId, $locked->id, UnlockSource::Credits);

    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);
    $this->get(ssvCallback($nonce))->assertOk();

    // Wiktoria's rule: credits must not pile up with nothing left to buy (canon
    // §8). The hard stop lives here, on the only repeatable earner.
    expect(creditsOfCouple($coupleId))->toBe(0)
        ->and(AdRewardNonce::query()->where('nonce', $nonce)->value('consumed_at'))->not->toBeNull();
});

test('an empty closed deck does not count as complete', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    // Nothing locked exists yet (an unseeded deck) — earning must keep working.
    $this->get(ssvCallback(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('an outage of Google key endpoint asks for a redelivery instead of dropping the reward', function (): void {
    $this->mock(AdMobSignatureVerifierInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->andThrow(new AdMobKeysUnavailableException('keys down'));
    });

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    // 503, not 200: the verdict was never reached, so Google must send it again.
    $this->get(ssvCallback($nonce))->assertStatus(503);

    expect(creditsOfCouple($coupleId))->toBe(0)
        // The nonce survives untouched, so the redelivery can still pay out.
        ->and(AdRewardNonce::query()->where('nonce', $nonce)->value('consumed_at'))->toBeNull();
});

test('a redelivery after the outage pays the reward', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    $nonce = IssueAdRewardNonceAction::run($coupleId, $user->id);

    $this->mock(AdMobSignatureVerifierInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->once()->andThrow(new AdMobKeysUnavailableException('keys down'));
        $mock->shouldReceive('verify')->once()->andReturn(true);
    });

    $this->get(ssvCallback($nonce))->assertStatus(503);
    $this->get(ssvCallback($nonce))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('the webhook needs no token', function (): void {
    fakeSignatureCheck(true);
    $user = createUserWithCouple();

    // Google holds no credentials — trust comes from the signature plus the nonce.
    $this->get(ssvCallback(IssueAdRewardNonceAction::run(activeCoupleOf($user)->id, $user->id)))
        ->assertOk()
        ->assertContent('');
});
