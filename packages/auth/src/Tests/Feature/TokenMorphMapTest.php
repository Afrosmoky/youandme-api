<?php

use Illuminate\Support\Facades\DB;
use Youandme\Auth\Models\User;

test('a freshly minted token stores the stable morph alias and authenticates', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    // The morph map rewrites the class name to the 'user' alias on write.
    $stored = DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->value('tokenable_type');
    expect($stored)->toBe('user');

    $this->withToken($token)->getJson('/api/v1/memories')->assertOk();
});

test('a legacy token row with the old App\\Models\\User FQCN resolves after normalisation', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    // Simulate a token minted before R1 moved User out of App\Models.
    DB::table('personal_access_tokens')
        ->where('tokenable_id', $user->id)
        ->update(['tokenable_type' => 'App\Models\User']);

    // Run the normalisation the data migration performs.
    DB::table('personal_access_tokens')
        ->whereIn('tokenable_type', ['App\Models\User', 'Youandme\Auth\Models\User'])
        ->update(['tokenable_type' => 'user']);

    $this->withToken($token)->getJson('/api/v1/memories')->assertOk();
});
