<?php

namespace App\Modules\Memories\Models;

use App\Modules\Catalog\Models\Question;
use Database\Factories\MemoryFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Youandme\Auth\Models\User;

class Memory extends Model
{
    /** @use HasFactory<MemoryFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /**
     * DB defaults are not loaded into the model on create(), so is_favorite is
     * declared here too — the P1 pattern (see CLAUDE.md). Change one, change both.
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_favorite' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'couple_id',
        'user_id',
        'question_id',
        'game_session_id',
        'origin',
        'answer_a',
        'answer_b',
        'player_a_name',
        'player_b_name',
        'is_favorite',
        'answered_at',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /** @var array<string, string> */
    protected $casts = [
        'answered_at' => 'datetime',
        'is_favorite' => 'boolean',
    ];

    protected static function newFactory(): MemoryFactory
    {
        return MemoryFactory::new();
    }

    /**
     * couple_id and game_session_id stay as plain int FK columns (cross-module
     * physical FKs, DR-009) — no Eloquent relations to Game, so Memories does not
     * depend on Game. question/user relations point at Catalog/Auth (allowed).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
