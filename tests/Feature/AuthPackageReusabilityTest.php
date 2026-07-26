<?php

use Symfony\Component\Finder\Finder;

/**
 * Guards the reusability of packages/auth: the referral (growth) mechanic must
 * not leak into the auth package (canon §4). Auth knows only "resolve a nick to
 * an id" and "mark first open" — never the words referral / referrer / credits,
 * and never a dependency on an app module.
 *
 * @return list<string>
 */
function authPackageSources(): array
{
    $files = [];
    foreach (Finder::create()->files()->in(base_path('packages/auth/src'))->name('*.php')->notPath('Tests') as $file) {
        $files[$file->getRelativePathname()] = $file->getContents();
    }

    return $files;
}

test('the auth package does not know the referral / credits mechanic', function (): void {
    foreach (authPackageSources() as $name => $contents) {
        expect($contents)
            ->not->toContain('credits')
            ->and(preg_match('/referr/i', $contents))->toBe(0, "packages/auth/src/{$name} mentions the referral mechanic");
    }
});

test('the auth package does not depend on any app module', function (): void {
    foreach (authPackageSources() as $name => $contents) {
        expect($contents)->not->toContain('App\\Modules', "packages/auth/src/{$name} imports an app module");
    }
});
