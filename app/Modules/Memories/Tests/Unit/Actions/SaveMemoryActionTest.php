<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Actions\SaveMemoryAction;
use App\Modules\Memories\Data\SaveMemoryInput;
use App\Modules\Memories\Events\MemoryCreated;
use App\Modules\Memories\Models\Memory;
use Illuminate\Support\Facades\Event;

function saveMemoryInputFor(int $coupleId, int $userId, string $questionUlid): SaveMemoryInput
{
    return new SaveMemoryInput(
        coupleId: $coupleId,
        questionUlid: $questionUlid,
        userId: $userId,
        playerAName: 'Ola',
        playerBName: null,
        answerA: 'moja odpowiedź',
        answerB: null,
        gameSessionId: null,
        origin: 'session',
        answeredAt: now(),
    );
}

test('creates a memory, resolves question id from ulid, returns the model', function (): void {
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create(['category_id' => Category::factory()->create()->id]);

    $memory = SaveMemoryAction::run(saveMemoryInputFor($couple->id, $user->id, $question->ulid));

    expect($memory)->toBeInstanceOf(Memory::class);
    expect($memory->question_id)->toBe($question->id);
    expect($memory->couple_id)->toBe($couple->id);
    expect($memory->answer_a)->toBe('moja odpowiedź');

    $this->assertDatabaseHas('memories', ['ulid' => $memory->ulid, 'question_id' => $question->id]);
});

test('dispatches MemoryCreated with a DTO payload', function (): void {
    Event::fake([MemoryCreated::class]);
    $user = createUserWithCouple();
    $couple = activeCoupleOf($user);
    $question = Question::factory()->create(['category_id' => Category::factory()->create()->id]);

    SaveMemoryAction::run(saveMemoryInputFor($couple->id, $user->id, $question->ulid));

    Event::assertDispatched(
        MemoryCreated::class,
        fn (MemoryCreated $e): bool => $e->data->questionUlid === $question->ulid
            && $e->data->coupleId === $couple->id,
    );
});
