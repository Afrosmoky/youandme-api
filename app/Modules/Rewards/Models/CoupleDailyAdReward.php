<?php

namespace App\Modules\Rewards\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A couple's rewarded-ad counter for one local day (Rewards child of the reward
 * account). The bucket is created lazily on the day's first claim and pruned once
 * it is older than the retention window.
 *
 * No ulid — the client never addresses it (same as CoupleReward). couple_id is a
 * cross-module FK to Game's couples (DR-009); Rewards holds the id as a value.
 */
class CoupleDailyAdReward extends Model
{
    /**
     * DB defaults are not loaded into the model on create(), so they are also
     * declared here — same deliberate P1 pattern as CoupleReward::$attributes.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'count' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
        'reward_date',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'reward_date' => 'date',
        'count' => 'integer',
    ];
}
