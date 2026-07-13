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
    ];

    /** @var array<string, string> */
    protected $casts = [
        'started_on' => 'date',
    ];

    protected static function newFactory(): CoupleWeeklyRitualFactory
    {
        return CoupleWeeklyRitualFactory::new();
    }
}
