<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Actions\UnlockQuestionForCoupleAction;
use App\Modules\Game\Support\UnlockSource;
use App\Modules\Rewards\Actions\IssueAdRewardNonceAction;
use App\Modules\Rewards\Support\AdMobSignatureVerifierInterface;
use Mockery\MockInterface;
use Youandme\Notifications\Actions\RegisterDeviceTokenAction;
use Youandme\Notifications\Data\PushMessageData;
use Youandme\Notifications\Support\PushSenderInterface;

/*
 | The P7 tail, end to end: a verified SSV callback credits the couple, Rewards
 | emits AdRewardGranted, the app-layer listener resolves the user and asks
 | Notifications to push. Both external sides (Google's signature, Google's FCM)
 | are faked; what is under test is the wiring between the three modules.
 */

beforeEach(function (): void {
    $this->mock(AdMobSignatureVerifierInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('verify')->andReturnTrue();
    });
});

function ssvCallbackFor(string $nonce): string
{
    return '/api/v1/webhooks/admob-ssv?'.http_build_query([
        'ad_network' => '5450213213286189855',
        'custom_data' => $nonce,
        'reward_amount' => '1',
        'transaction_id' => '0123456789ABCDEF',
    ]).'&signature=c2ln&key_id=1234567890';
}

test('a granted ad reward pushes the user who watched it', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RegisterDeviceTokenAction::run($user->ulid, 'phone-token', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')
            ->once()
            ->withArgs(function (string $token, PushMessageData $message): bool {
                return $token === 'phone-token'
                    && $message->data['type'] === 'ad_reward_granted'
                    && $message->data['amount'] === '1'
                    && $message->title !== '';
            })
            ->andReturnTrue();
    });

    $this->get(ssvCallbackFor(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('a replayed callback pushes only once', function (): void {
    $user = createUserWithCouple();
    RegisterDeviceTokenAction::run($user->ulid, 'phone-token', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->andReturnTrue();
    });

    $callback = ssvCallbackFor(IssueAdRewardNonceAction::run(activeCoupleOf($user)->id, $user->id));

    $this->get($callback)->assertOk();
    $this->get($callback)->assertOk();
});

test('nothing is pushed when nothing was granted', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RegisterDeviceTokenAction::run($user->ulid, 'phone-token', 'android');

    // Deck complete → the view earns nothing, so there is nothing to announce.
    $locked = Question::factory()->locked()->create();
    UnlockQuestionForCoupleAction::run($coupleId, $locked->id, UnlockSource::Credits);

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });

    $this->get(ssvCallbackFor(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();
});

test('a user without a registered device still gets the credit', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldNotReceive('send');
    });

    $this->get(ssvCallbackFor(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('an iOS user is pushed too, and every device of a mixed pair hears it', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RegisterDeviceTokenAction::run($user->ulid, 'iphone-token', 'ios');
    RegisterDeviceTokenAction::run($user->ulid, 'android-token', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        // T11 (#24): APNs is configured, so the Android-only filter is gone and a
        // phone hears about its credit whichever platform it runs.
        $mock->shouldReceive('send')->once()->with('iphone-token', Mockery::any())->andReturnTrue();
        $mock->shouldReceive('send')->once()->with('android-token', Mockery::any())->andReturnTrue();
    });

    $this->get(ssvCallbackFor(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('a failing push does not cost the credit or the callback', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    RegisterDeviceTokenAction::run($user->ulid, 'phone-token', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->andReturnFalse();
    });

    // Push is a side channel: Google still gets its 200 and the couple keeps the
    // credit it earned.
    $this->get(ssvCallbackFor(IssueAdRewardNonceAction::run($coupleId, $user->id)))->assertOk();

    expect(creditsOfCouple($coupleId))->toBe(1);
});

test('the push goes to the watcher, not to the other member of the couple', function (): void {
    $watcher = createUserWithCouple();
    $partner = createUserWithCouple();
    RegisterDeviceTokenAction::run($watcher->ulid, 'watcher-phone', 'android');
    RegisterDeviceTokenAction::run($partner->ulid, 'partner-phone', 'android');

    $this->mock(PushSenderInterface::class, function (MockInterface $mock): void {
        $mock->shouldReceive('send')->once()->with('watcher-phone', Mockery::any())->andReturnTrue();
        $mock->shouldNotReceive('send')->with('partner-phone', Mockery::any());
    });

    $this->get(ssvCallbackFor(
        IssueAdRewardNonceAction::run(activeCoupleOf($watcher)->id, $watcher->id)
    ))->assertOk();
});
