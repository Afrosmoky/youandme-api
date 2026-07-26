<?php

use Symfony\Component\Finder\Finder;

/**
 * Guards the shape of the Rewards extraction: Rewards is a LEAF (Rewards → ∅).
 * It is credited with a couple id passed as a plain value, so it never imports
 * Game / Catalog / Memories or the reusable packages — that is what keeps the
 * new edges (Game → Rewards, app → Rewards) from closing a cycle.
 *
 * Sources only; Tests/ is excluded (an endpoint test legitimately asserts that
 * the share reward leaves Game state alone) — same policy as the auth package
 * reusability guard.
 *
 * @return array<string, string>
 */
function rewardsModuleSources(): array
{
    $files = [];
    foreach (Finder::create()->files()->in(app_path('Modules/Rewards'))->name('*.php')->notPath('Tests') as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

test('the Rewards module is a leaf — it depends on no other module or package', function (): void {
    foreach (rewardsModuleSources() as $name => $contents) {
        expect($contents)
            ->not->toContain('App\\Modules\\Game', "app/Modules/Rewards/{$name} imports Game")
            ->and($contents)->not->toContain('App\\Modules\\Catalog', "app/Modules/Rewards/{$name} imports Catalog")
            ->and($contents)->not->toContain('App\\Modules\\Memories', "app/Modules/Rewards/{$name} imports Memories")
            ->and($contents)->not->toContain('Youandme\\', "app/Modules/Rewards/{$name} imports a reusable package");
    }
});

test('the reusable packages do not know the Rewards module', function (): void {
    $files = Finder::create()->files()
        ->in([base_path('packages/auth/src'), base_path('packages/notifications/src')])
        ->name('*.php')
        ->notPath('Tests');

    foreach ($files as $file) {
        expect($file->getContents())
            ->not->toContain('App\\Modules\\Rewards', "{$file->getRelativePathname()} imports the Rewards module");
    }
});
