<?php

use App\Modules\Rewards\Models\AdRewardNonce;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | POST /ad-reward/nonce — step 1 of the SSV handshake. It authorizes a view; it
 | never grants anything (that is the webhook's job).
 */

test('a nonce is issued and bound to the caller couple server-side', function (): void {
    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    Sanctum::actingAs($user);

    $nonce = $this->postJson('/api/v1/ad-reward/nonce')
        ->assertCreated()
        ->json('nonce');

    expect($nonce)->toBeString()->not->toBeEmpty();

    $row = AdRewardNonce::query()->where('nonce', $nonce)->firstOrFail();

    expect((int) $row->couple_id)->toBe($coupleId)
        ->and((int) $row->user_id)->toBe($user->id)
        ->and($row->consumed_at)->toBeNull();
});

test('asking for a nonce grants no credits', function (): void {
    $user = createUserWithCouple();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/ad-reward/nonce')->assertCreated();

    expect(creditsOfCouple(activeCoupleOf($user)->id))->toBe(0);
});

test('every ad gets its own nonce', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $first = $this->postJson('/api/v1/ad-reward/nonce')->json('nonce');
    $second = $this->postJson('/api/v1/ad-reward/nonce')->json('nonce');

    expect($first)->not->toBe($second)
        ->and(AdRewardNonce::query()->count())->toBe(2);
});

test('the P6 client grant endpoint is gone', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    // Removed without a deprecation window (canon §5): as long as it answers, the
    // spoof it enabled is still live.
    $this->postJson('/api/v1/ad-reward')->assertNotFound();
});

test('nonce requests are throttled so the table cannot be padded', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    foreach (range(1, 20) as $ignored) {
        $this->postJson('/api/v1/ad-reward/nonce')->assertCreated();
    }

    $this->postJson('/api/v1/ad-reward/nonce')->assertStatus(429);

    expect(AdRewardNonce::query()->count())->toBe(20);
});

test('issuing a nonce requires a token', function (): void {
    $this->postJson('/api/v1/ad-reward/nonce')->assertUnauthorized();
});

test('a user without a couple gets no nonce', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/ad-reward/nonce')->assertNotFound();
});
