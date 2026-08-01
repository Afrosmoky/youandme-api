<?php

namespace App\Modules\Progress\Models;

use Database\Factories\ProgressMilestoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * One stage of the progress map: a card count and what reaching it is called.
 *
 * No ulid — slug is the public identifier (the categories precedent). The model
 * carries no per-couple state; whether a given couple reached this milestone
 * lives in couple_milestone_unlocks.
 */
class ProgressMilestone extends Model
{
    /** @use HasFactory<ProgressMilestoneFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'slug',
        'name',
        'threshold',
        'ordering',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'threshold' => 'integer',
        'ordering' => 'integer',
    ];

    protected static function newFactory(): ProgressMilestoneFactory
    {
        return ProgressMilestoneFactory::new();
    }
}
