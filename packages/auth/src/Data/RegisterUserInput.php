<?php

namespace Youandme\Auth\Data;

use Spatie\LaravelData\Data;

/**
 * Input for RegisterUserAction. Built from the already-validated
 * RegisterRequest payload.
 */
class RegisterUserInput extends Data
{
    public function __construct(
        public string $email,
        public string $password,
        public string $nickname,
    ) {}
}
