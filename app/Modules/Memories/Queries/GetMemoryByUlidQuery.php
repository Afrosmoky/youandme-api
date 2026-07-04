<?php

namespace App\Modules\Memories\Queries;

use App\Modules\Memories\Data\MemoryData;
use App\Modules\Memories\Models\Memory;
use Lorisleiva\Actions\Concerns\AsAction;

final class GetMemoryByUlidQuery
{
    use AsAction;

    public function handle(string $ulid): ?MemoryData
    {
        $memory = Memory::query()->with('question.category')->where('ulid', $ulid)->first();

        return $memory ? MemoryData::fromModel($memory) : null;
    }
}
