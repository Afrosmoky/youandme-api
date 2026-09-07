<?php

namespace App\Modules\Game\Models;

use Database\Factories\CoupleWeeklyRitualFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A couple's weekly ritual assignment (Game aggregate child). No ulid — it is not
 * addressed from the client. ritual_id is a plain cross-module FK to Catalog's
 * rituals; the content is resolved via Catalog::GetRitualByIdQuery, never by
 * loading the Ritual model here.
 *
 * completed_at is the couple's "we did it" marker (nullable — null means not yet).
 * It lives on the assignment, not on the couple, so last week's confirmation stays
 * in last week's row and a new week starts uncompleted without anything resetting.
 */
class CoupleWeeklyRitual extends Model
{
    /** @use HasFactory<CoupleWeeklyRitualFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
        'ritual_id',
        'started_on',
        'completed_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_on' => 'date',
        'completed_at' => 'datetime',
    ];

    protected static function newFactory(): CoupleWeeklyRitualFactory
    {
        return CoupleWeeklyRitualFactory::new();
    }
}
