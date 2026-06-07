<?php

namespace App\Models;

use Database\Factories\CoupleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Couple extends Model
{
    /** @use HasFactory<CoupleFactory> */
    use HasFactory, HasUlids;

    /**
     * DB defaults are not loaded into the model on create(), so they are also
     * declared here — same deliberate P1 pattern as User::timezone. See CLAUDE.md.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'streak_current' => 0,
        'streak_longest' => 0,
        'daily_push_hour' => 20,
    ];

    /** @var list<string> */
    protected $fillable = [
        'user_a_id',
        'user_b_id',
        'partner_name_local',
        'relationship_started_on',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'relationship_started_on' => 'date',
        'last_daily_answered_on' => 'date',
        'streak_current' => 'integer',
        'streak_longest' => 'integer',
        'daily_push_hour' => 'integer',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_a_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userB(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_b_id');
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        // dochodzi w fazie 1 krok 6 - refaktor memories (couple_id na memories)
        return $this->hasMany(Memory::class);
    }

    // gameSessions(): hasMany(GameSession::class) -- dochodzi w fazie 1 krok 5
}
