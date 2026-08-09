<?php

namespace App\Modules\Game\Models;

use App\Modules\Catalog\Models\Category;
use App\Modules\Memories\Models\Memory;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One server-sequenced run through a pool of questions (state = remaining ids +
 * where the couple is in them).
 *
 * RESERVED FOR SOLO (etap III) — kept deliberately, not dead code. Since P10 the
 * couple game runs on the phone and reports afterwards, and P11 S3a stops mobile
 * calling the session endpoints altogether, so no client creates rows here any
 * more. The table and the model stay because the solo game needs precisely this:
 * server-held order and position for a player the client cannot be trusted to
 * sequence. See SessionController for the same note.
 */
class GameSession extends Model
{
    /** @use HasFactory<GameSessionFactory> */
    use HasFactory, HasUlids;

    /**
     * DB defaults are not loaded into the model on create() — declare them here
     * too (same P3 pattern as Couple). See CLAUDE.md.
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'cards_drawn_count' => 0,
        'cards_saved_count' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
        'mode',
        'category_id',
        'state',
        'started_at',
        'ended_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'state' => 'array',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'cards_drawn_count' => 'integer',
        'cards_saved_count' => 'integer',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function newFactory(): GameSessionFactory
    {
        return GameSessionFactory::new();
    }

    /**
     * @return BelongsTo<Couple, $this>
     */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /**
     * Cross-module relation to Catalog's Category (game_sessions.category_id).
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * TODO Etap 5 (Memories): Memory lives in App\Models until extracted.
     *
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @param  Builder<GameSession>  $query
     * @return Builder<GameSession>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }
}
