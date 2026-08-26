<?php

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Question;

test('seeder loads 100 questions from the deck', function (): void {
    $this->seed();

    expect(Question::count())->toBeGreaterThanOrEqual(100);
});

test('seeder assigns categories correctly', function (): void {
    $this->seed();

    $naPoznanie = Category::where('slug', 'na_poznanie')->firstOrFail();

    expect(Question::where('category_id', $naPoznanie->id)->count())
        ->toBeGreaterThanOrEqual(60);
});

test('seeder populates tags', function (): void {
    $this->seed();

    expect(Question::whereJsonLength('tags', '>', 0)->count())->toBeGreaterThan(0);
});

test('the deck ships forty paid cards and sixty free', function (): void {
    $this->seed();

    $sessionDeck = Question::where('type', 'session')->where('locale', 'pl');

    expect((clone $sessionDeck)->where('is_locked', true)->count())->toBe(40)
        ->and((clone $sessionDeck)->where('is_locked', false)->count())->toBe(60);
});

test('re-seeding does not reshuffle which cards are locked', function (): void {
    $this->seed();
    $lockedUlids = Question::where('is_locked', true)->orderBy('id')->pluck('ulid')->all();

    $this->seed();

    expect(Question::where('is_locked', true)->orderBy('id')->pluck('ulid')->all())
        ->toBe($lockedUlids);
});

test('the daily deck stays free', function (): void {
    $this->seed();

    expect(Question::where('type', 'daily')->where('is_locked', true)->count())->toBe(0);
});

test('seeder attaches answer options to exactly 14 cards', function (): void {
    $this->seed();

    // 14, not 15: the fifteenth multiple-choice card spells its options out inside
    // its own body, and rewriting a body is a content change (and a re-key of the
    // seed) that belongs to its own slice.
    expect(Question::whereNotNull('options')->count())->toBe(14)
        ->and(Question::where('type', 'daily')->whereNotNull('options')->count())->toBe(0);
});

test('every options entry points at a seed_key the deck carries', function (): void {
    $deckPath = base_path('app/Modules/Catalog/Database/Seeders/data/questions_with_categories_pl.json');
    $optionsPath = base_path('app/Modules/Catalog/Database/Seeders/data/question_options_pl.json');

    /** @var list<array{seed_key: string}> $deck */
    $deck = json_decode((string) file_get_contents($deckPath), true, flags: JSON_THROW_ON_ERROR);
    /** @var list<array{seed_key: string}> $options */
    $options = json_decode((string) file_get_contents($optionsPath), true, flags: JSON_THROW_ON_ERROR);

    $keys = array_column($deck, 'seed_key');

    // The two files used to be joined on the whole question body — the one string
    // a re-export of Wiktoria's docx is most likely to change, which would have
    // detached the answers from fourteen cards without a word. Since (d) the join
    // is the key, and this guards the key instead.
    $orphans = array_values(array_filter(
        array_column($options, 'seed_key'),
        fn (string $key): bool => ! in_array($key, $keys, true),
    ));

    expect($orphans)->toBe([])
        ->and($options)->toHaveCount(14);
});

test('the multi-select card is the only one flagged multiple', function (): void {
    $this->seed();

    $cards = Question::whereNotNull('options')->get();
    $multi = $cards->filter(fn (Question $question): bool => $question->options['multiple'] === true);

    expect($multi)->toHaveCount(1)
        ->and($multi->first()?->body)->toStartWith('Co stanowi dla Ciebie najlepsze wsparcie')
        ->and($multi->first()?->options['items'])->toHaveCount(4);
});

test('options are stored as an items plus multiple envelope', function (): void {
    $this->seed();

    $card = Question::whereNotNull('options')->firstOrFail();

    expect(array_keys($card->options))->toBe(['items', 'multiple'])
        ->and($card->options['items'])->toBeArray()->not->toBeEmpty()
        ->and($card->options['multiple'])->toBeBool();
});
