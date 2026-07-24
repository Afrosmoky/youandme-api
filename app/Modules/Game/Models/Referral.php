<?php

namespace App\Modules\Game\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Referral relation (Game). Holds only the two user ids (cross-module FKs to
 * Auth's users, DR-009) and the referrer payout state — deliberately no
 * belongsTo(User): Game never loads User with Eloquent. Stateful (referrer_awarded_at),
 * which is why this is a model and not a plain pivot. No ulid, no timestamps.
 *
 * @property int $id
 * @property int $referrer_user_id
 * @property int $referred_user_id
 * @property Carbon|null $referrer_awarded_at
 */
class Referral extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'referrer_user_id',
        'referred_user_id',
        'referrer_awarded_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'referrer_awarded_at' => 'datetime',
    ];
}
