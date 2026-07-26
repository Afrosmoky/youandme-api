<?php

namespace App\Modules\Rewards\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A couple's reward account (Rewards aggregate root): the earned-credits balance
 * plus the one-time claim flags. One row per couple (unique couple_id), created
 * lazily on the first grant.
 *
 * No ulid — it is not addressed from the client (same as couple_weekly_rituals).
 * couple_id is a plain cross-module FK to Game's couples (DR-009); Rewards holds
 * the id as a value and never loads the Couple model.
 */
class CoupleReward extends Model
{
    /**
     * DB defaults are not loaded into the model on create(), so they are also
     * declared here — same deliberate P1 pattern as Couple::$attributes.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'credits' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'credits' => 'integer',
        'share_reward_claimed_at' => 'datetime',
    ];
}
