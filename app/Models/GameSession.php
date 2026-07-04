<?php

namespace App\Models;

use App\Modules\Catalog\Models\Category;
use Database\Factories\GameSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * @return BelongsTo<Couple, $this>
     */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
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
