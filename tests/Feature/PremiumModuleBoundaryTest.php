<?php

use Symfony\Component\Finder\Finder;

/**
 * Guards the central claim of P7's new module: Premium is a LEAF (Premium → ∅).
 *
 * A new capability normally means a new edge in the graph. This one does not,
 * because the entitlement a code buys lives in Game and the app layer does the
 * joining — Premium only validates the code and records who used it. If this test
 * ever fails, that design has quietly been abandoned.
 *
 * Sources only; Tests/ is excluded — same policy as the Rewards and Auth guards.
 */
test('the Premium module is a leaf — it depends on no other module or package', function (): void {
    $files = Finder::create()->files()->in(app_path('Modules/Premium'))->name('*.php')->notPath('Tests');

    foreach ($files as $file) {
        $name = $file->getRelativePathname();

        expect($file->getContents())
            ->not->toContain('App\\Modules\\Game', "app/Modules/Premium/{$name} imports Game")
            ->and($file->getContents())->not->toContain('App\\Modules\\Catalog', "app/Modules/Premium/{$name} imports Catalog")
            ->and($file->getContents())->not->toContain('App\\Modules\\Memories', "app/Modules/Premium/{$name} imports Memories")
            ->and($file->getContents())->not->toContain('App\\Modules\\Rewards', "app/Modules/Premium/{$name} imports Rewards")
            ->and($file->getContents())->not->toContain('Youandme\\', "app/Modules/Premium/{$name} imports a reusable package");
    }
});

test('no other module knows the Premium module', function (): void {
    $files = Finder::create()->files()
        ->in([
            app_path('Modules/Game'),
            app_path('Modules/Catalog'),
            app_path('Modules/Memories'),
            app_path('Modules/Rewards'),
            base_path('packages/auth/src'),
            base_path('packages/notifications/src'),
        ])
        ->name('*.php')
        ->notPath('Tests');

    // Only the app layer may name Premium — that is what keeps the redeem flow
    // from turning into a dependency between modules.
    foreach ($files as $file) {
        expect($file->getContents())
            ->not->toContain('App\\Modules\\Premium', "{$file->getRelativePathname()} imports the Premium module");
    }
});
