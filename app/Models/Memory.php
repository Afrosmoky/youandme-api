<?php

namespace App\Models;

use App\Modules\Catalog\Models\Question;
use App\Modules\Game\Models\Couple;
use App\Modules\Game\Models\GameSession;
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
    ];

    /**
     * @return BelongsTo<Couple, $this>
     */
    public function couple(): BelongsTo
    {
        return $this->belongsTo(Couple::class);
    }

    /**
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

    /**
     * @return BelongsTo<GameSession, $this>
     */
    public function gameSession(): BelongsTo
    {
        return $this->belongsTo(GameSession::class);
    }
}
