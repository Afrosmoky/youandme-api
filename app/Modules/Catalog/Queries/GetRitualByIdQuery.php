<?php

namespace App\Modules\Catalog\Queries;

use App\Modules\Catalog\Data\RitualData;
use App\Modules\Catalog\Models\Ritual;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Internal id → RitualData. Game holds ritual_id on couple_weekly_rituals and
 * resolves the content back to its public contract through this.
 */
final class GetRitualByIdQuery
{
    use AsAction;

    public function handle(int $id): ?RitualData
    {
        $ritual = Ritual::query()->find($id);

        return $ritual ? RitualData::fromModel($ritual) : null;
    }
}
