<?php

namespace App\Modules\Game\Data;

use Spatie\LaravelData\Data;

final class StartSessionInput extends Data
{
    public function __construct(
        public readonly int $coupleId,
        public readonly ?string $categorySlug,
    ) {}
}
