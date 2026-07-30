<?php

namespace App\Modules\Rewards\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A one-time token authorizing one rewarded-ad grant for one couple (Rewards
 * child of the reward account). Written when the client asks for an ad, burned
 * when the SSV callback for it arrives.
 *
 * No ulid — the nonce string is the public address. couple_id / user_id are
 * cross-module FKs held as plain values (DR-009): Rewards resolves the couple
 * from its own row, which is exactly what stops a manipulated client from
 * crediting somebody else.
 */
class AdRewardNonce extends Model
{
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
        'user_id',
        'nonce',
        'issued_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'issued_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];
}
