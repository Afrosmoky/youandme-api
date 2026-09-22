<?php

use App\Modules\Game\Models\Couple;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Exception\RuntimeException;
use Youandme\Auth\Models\User;

test('it deletes the account after confirmation', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);
    seedAccountFootprint($user);

    $this->artisan('account:delete', ['email' => 'OLA@example.com'])
        ->expectsConfirmation('Delete this account and all of its data permanently? This cannot be undone.', 'yes')
        ->expectsOutput("Account {$user->ulid} deleted.")
        ->assertSuccessful();

    expect(User::withTrashed()->whereKey($user->id)->exists())->toBeFalse();
});

test('declining the confirmation deletes nothing', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);

    $this->artisan('account:delete', ['email' => 'ola@example.com'])
        ->expectsConfirmation('Delete this account and all of its data permanently? This cannot be undone.', 'no')
        ->expectsOutput('Nothing deleted.')
        ->assertFailed();

    expect($user->fresh())->not->toBeNull();
});

test('without interaction nothing is deleted', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);

    $exitCode = Artisan::call('account:delete', ['email' => 'ola@example.com', '--no-interaction' => true]);

    expect($exitCode)->toBe(1);

    expect($user->fresh())->not->toBeNull();
});

test('it cannot run without an e-mail', function (): void {
    createUserWithCouple();

    expect(fn () => $this->artisan('account:delete')->run())
        ->toThrow(RuntimeException::class, 'Not enough arguments');
});

test('an unknown e-mail is reported and deletes nothing', function (): void {
    $user = createUserWithCouple();

    $this->artisan('account:delete', ['email' => 'nobody@example.com'])
        ->expectsOutput('No account with this e-mail address.')
        ->assertFailed();

    expect($user->fresh())->not->toBeNull();
});

test('an account in a shared couple is refused', function (): void {
    $user = createUserWithCouple(['email' => 'ola@example.com']);
    Couple::factory()->create(['user_a_id' => createUserWithCouple()->id, 'user_b_id' => $user->id]);

    $this->artisan('account:delete', ['email' => 'ola@example.com'])
        ->expectsConfirmation('Delete this account and all of its data permanently? This cannot be undone.', 'yes')
        ->assertFailed();

    expect($user->fresh())->not->toBeNull();
});
