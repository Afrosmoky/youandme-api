<?php

namespace App\Modules\Rewards\Actions;

use App\Modules\Rewards\Support\AdMobSignatureVerifierInterface;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Did Google really send this callback?" — the public entry point for signature
 * checking, so the app-layer webhook depends on Rewards' contract rather than on
 * an OpenSSL adapter (canon §1 row 8: verification belongs to Rewards, only the
 * route and the orchestration are app's).
 *
 * The verifier is injected, so tests bind a fake and never touch the network —
 * the same shape as the social token verifiers in Auth.
 */
final class VerifyAdMobCallbackAction
{
    use AsAction;

    public function __construct(
        private readonly AdMobSignatureVerifierInterface $verifier,
    ) {}

    public function handle(string $signedData, string $signature, string $keyId): bool
    {
        return $this->verifier->verify($signedData, $signature, $keyId);
    }
}
