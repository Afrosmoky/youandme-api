<?php

use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Progress\Models\ProgressMilestone;
use Laravel\Sanctum\Sanctum;
use Youandme\Auth\Models\User;

/*
 | GET /progress — the app layer joining a count from Memories with the map from
 | Progress. Neither module knows the other; this endpoint is the seam.
 */

function playCards(User $user, int $coupleId, int $count): void
{
    foreach (range(1, $count) as $ignored) {
        SaveMemoryAction::run(new SaveMemoryInput(
            coupleId: $coupleId,
            questionUlid: Question::factory()->create()->ulid,
            userId: $user->id,
            playerAName: $user->nickname,
            playerBName: 'Partner',
            answerA: 'odpowiedź',
            answerB: null,
            gameSessionId: null,
            origin: 'session',
            answeredAt: now(),
        ));
    }
}

test('a couple that has played nothing sees the whole map ahead of them', function (): void {
    ProgressMilestone::factory()->at(10)->create(['ordering' => 1, 'name' => 'Pierwsze iskry']);
    ProgressMilestone::factory()->at(25)->create(['ordering' => 2, 'name' => 'Wspólny rytm']);

    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('total_played', 0)
        ->assertJsonPath('next_threshold', 10)
        ->assertJsonCount(2, 'milestones')
        ->assertJsonPath('milestones.0.unlocked', false)
        ->assertJsonPath('milestones.0.unlocked_at', null)
        // Locked stages are named — the map shows what lies ahead (canon §6).
        ->assertJsonPath('milestones.0.name', 'Pierwsze iskry')
        ->assertJsonPath('milestones.1.name', 'Wspólny rytm');
});

test('played cards move the map', function (): void {
    ProgressMilestone::factory()->at(2)->create(['ordering' => 1]);
    ProgressMilestone::factory()->at(10)->create(['ordering' => 2]);

    $user = createUserWithCouple();
    $coupleId = activeCoupleOf($user)->id;
    playCards($user, $coupleId, 3);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('total_played', 3)
        ->assertJsonPath('next_threshold', 10)
        ->assertJsonPath('milestones.0.unlocked', true)
        ->assertJsonPath('milestones.1.unlocked', false);

    expect($this->getJson('/api/v1/progress')->json('milestones.0.unlocked_at'))->toEndWith('Z');
});

test('a finished map reports no next threshold', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $user = createUserWithCouple();
    playCards($user, activeCoupleOf($user)->id, 2);

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('next_threshold', null)
        ->assertJsonPath('milestones.0.unlocked', true);
});

test('the map comes in map order, not threshold order', function (): void {
    ProgressMilestone::factory()->at(50)->create(['ordering' => 1, 'slug' => 'pierwszy']);
    ProgressMilestone::factory()->at(10)->create(['ordering' => 2, 'slug' => 'drugi']);

    Sanctum::actingAs(createUserWithCouple());

    $response = $this->getJson('/api/v1/progress')->assertOk();

    expect($response->json('milestones.*.slug'))->toBe(['pierwszy', 'drugi'])
        // Still the closest one ahead, whatever the map order says.
        ->and($response->json('next_threshold'))->toBe(10);
});

test('the map is per couple', function (): void {
    ProgressMilestone::factory()->at(1)->create();

    $player = createUserWithCouple();
    playCards($player, activeCoupleOf($player)->id, 1);

    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertJsonPath('total_played', 0)
        ->assertJsonPath('milestones.0.unlocked', false);
});

test('an unseeded map is empty rather than broken', function (): void {
    Sanctum::actingAs(createUserWithCouple());

    $this->getJson('/api/v1/progress')
        ->assertOk()
        ->assertExactJson([
            'total_played' => 0,
            'next_threshold' => null,
            'milestones' => [],
        ]);
});

test('the map requires a token', function (): void {
    $this->getJson('/api/v1/progress')->assertUnauthorized();
});

test('a user without a couple has no map', function (): void {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/v1/progress')->assertNotFound();
});
