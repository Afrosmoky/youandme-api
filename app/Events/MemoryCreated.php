<?php

namespace App\Events;

use App\Models\Memory;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MemoryCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Memory $memory,
    ) {}
}
