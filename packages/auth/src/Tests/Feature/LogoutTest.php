<?php

use Youandme\Auth\Models\User;

test('logout revokes the current token and returns 204', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    $response->assertNoContent();
    expect($user->tokens()->count())->toBe(0);
});

test('a revoked token can no longer access protected routes', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('mobile')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

    // The auth manager caches the resolved guard user across requests within a
    // single test (in production each request is a fresh process). Forget the
    // guards so the next request re-resolves the now-deleted token.
    $this->app['auth']->forgetGuards();

    $this->withToken($token)->getJson('/api/v1/memories')->assertUnauthorized();
});

test('logout requires authentication', function (): void {
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
});
