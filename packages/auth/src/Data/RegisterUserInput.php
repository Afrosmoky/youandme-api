<?php

namespace Youandme\Auth\Data;

use Spatie\LaravelData\Data;

/**
 * Input for RegisterUserAction. Built from the already-validated
 * RegisterRequest payload.
 */
final class RegisterUserInput extends Data
{
    public function __construct(
        public readonly string $email,
        public readonly string $password,
        public readonly string $nickname,
    ) {}
}
