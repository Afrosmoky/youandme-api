<?php

namespace App\Modules\Progress\Database\Seeders;

use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Database\Seeder;

class ProgressMilestoneSeeder extends Seeder
{
    /**
     * The seven stages of the progress map, one per node on the mobile map.
     *
     * The thresholds are no longer working values: they are fitted to the deck
     * the MVP actually ships. What the map counts is the couple's played set
     * (couple_question_seen), which only session cards enter — the daily card has
     * the streak instead — so its ceiling is the size of the session deck, 100
     * cards, of which the last 40 are locked (QuestionSeeder::LOCKED_TAIL).
     *
     * That ceiling is the whole shape of this list. The old thresholds ran to 365
     * and promised a journey nobody could take: three of the seven nodes were
     * arithmetically out of reach and a fourth needed the closed deck bought out.
     * Now the ladder ends where the content does:
     *
     *   5..45  the free deck, five stages a couple reaches by playing
     *   60     the end of the free deck — everything open, played
     *   80,100 only with the closed part unlocked (P7 credits)
     *
     * Names are still Wiktoria's to finish (#36); changing one is safe, because
     * everything downstream keys on the slug — the mobile celebration detector
     * deliberately remembers slugs so a rewritten word does not read as a new
     * milestone.
     *
     * Re-seeding is safe in both directions: it is keyed on slug, and because
     * unlocks are monotonic, raising a threshold never takes a milestone back
     * while lowering one is picked up by the next played card
     * (CheckMilestonesAction writes everything crossed, not just the newest).
     * That repair happens on the next card, though, and GET /progress is a pure
     * read that will not trigger it — so after a change like this one, run
     * `php artisan progress:backfill-played` to settle existing couples at once.
     *
     * @var list<array{slug: string, name: string, threshold: int}>
     */
    private const MILESTONES = [
        ['slug' => 'pierwsze_iskry', 'name' => 'Pierwsze iskry', 'threshold' => 5],
        ['slug' => 'wspolny_rytm', 'name' => 'Wspólny rytm', 'threshold' => 15],
        ['slug' => 'glebsza_rozmowa', 'name' => 'Głębsza rozmowa', 'threshold' => 30],
        ['slug' => 'pokrewienstwo_dusz', 'name' => 'Pokrewieństwo dusz', 'threshold' => 45],
        ['slug' => 'splecione_losy', 'name' => 'Splecione losy', 'threshold' => 60],
        ['slug' => 'zaufanie', 'name' => 'Zaufanie', 'threshold' => 80],
        ['slug' => 'bez_slow', 'name' => 'Bez słów', 'threshold' => 100],
    ];

    public function run(): void
    {
        foreach (self::MILESTONES as $index => $milestone) {
            ProgressMilestone::updateOrCreate(
                ['slug' => $milestone['slug']],
                [
                    'name' => $milestone['name'],
                    'threshold' => $milestone['threshold'],
                    // Position in the list is the map order — the dictionary is
                    // written in the order the couple travels it.
                    'ordering' => $index + 1,
                ],
            );
        }
    }
}
