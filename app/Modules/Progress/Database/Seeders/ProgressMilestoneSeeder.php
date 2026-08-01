<?php

namespace App\Modules\Progress\Database\Seeders;

use App\Modules\Progress\Models\ProgressMilestone;
use Illuminate\Database\Seeder;

class ProgressMilestoneSeeder extends Seeder
{
    /**
     * The seven stages of the progress map, one per node on the mobile map.
     *
     * Thresholds and names are WORKING VALUES, to be replaced when the content is
     * settled. Re-seeding is safe either way: it is keyed on slug, and because
     * unlocks are monotonic, raising a threshold never takes a milestone back
     * while lowering one is picked up by the next saved card (CheckMilestonesAction
     * writes everything crossed, not just the newest).
     *
     * @var list<array{slug: string, name: string, threshold: int}>
     */
    private const MILESTONES = [
        ['slug' => 'pierwsze_iskry', 'name' => 'Pierwsze iskry', 'threshold' => 10],
        ['slug' => 'wspolny_rytm', 'name' => 'Wspólny rytm', 'threshold' => 25],
        ['slug' => 'glebsza_rozmowa', 'name' => 'Głębsza rozmowa', 'threshold' => 50],
        ['slug' => 'pokrewienstwo_dusz', 'name' => 'Pokrewieństwo dusz', 'threshold' => 100],
        ['slug' => 'splecione_losy', 'name' => 'Splecione losy', 'threshold' => 200],
        ['slug' => 'zaufanie', 'name' => 'Zaufanie', 'threshold' => 300],
        ['slug' => 'bez_slow', 'name' => 'Bez słów', 'threshold' => 365],
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
