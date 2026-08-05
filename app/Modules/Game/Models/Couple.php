<?php

namespace App\Modules\Game\Models;

use App\Modules\Catalog\Models\Question;
use App\Modules\Memories\Models\Memory;
use Database\Factories\CoupleFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Youandme\Auth\Models\User;

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

    protected static function newFactory(): CoupleFactory
    {
        return CoupleFactory::new();
    }

    /**
     * Cross-module relation to Auth's User (couples.user_a_id FK → users.id,
     * allowed per DR-009). Logical reads across modules go through Query classes.
     *
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
     * TODO Etap 5 (Memories): Memory lives in App\Models until extracted.
     *
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @return HasMany<GameSession, $this>
     */
    public function gameSessions(): HasMany
    {
        return $this->hasMany(GameSession::class);
    }

    /**
     * @return HasMany<CoupleWeeklyRitual, $this>
     */
    public function weeklyRituals(): HasMany
    {
        return $this->hasMany(CoupleWeeklyRitual::class);
    }

    /**
     * The cards this couple has played — the persistent set behind two things at
     * once (couple_question_seen pivot, no Eloquent model). Cross-module relation
     * to Catalog's Question.
     *
     * P3 built it as the session anti-repeat log: a question, once shown, never
     * returns. P10 gave the same rows a second meaning — the progress map counts
     * them — without changing the schema. Nothing writes here directly any more;
     * MarkQuestionsPlayedAction is the single door, and the daily card is
     * deliberately outside it (the map counts the question game, the daily card
     * has the streak).
     *
     * @return BelongsToMany<Question, $this>
     */
    public function seenQuestions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'couple_question_seen')
            ->withPivot('seen_at');
    }

    /**
     * Questions this couple has liked — intentional hearts (couple_question_likes
     * pivot, no Eloquent model). A disjoint relation from seenQuestions: liking a
     * question does not mark it seen and vice versa.
     *
     * @return BelongsToMany<Question, $this>
     */
    public function likedQuestions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'couple_question_likes')
            ->withPivot('liked_at');
    }

    /**
     * Locked questions this couple may play — the entitlement written when a
     * credit is spent or a promo code is redeemed (couple_unlocked_questions
     * pivot, no Eloquent model). Disjoint from seenQuestions and likedQuestions:
     * unlocking says "you may draw this card", not "you have drawn it".
     *
     * @return BelongsToMany<Question, $this>
     */
    public function unlockedQuestions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'couple_unlocked_questions')
            ->withPivot(['unlocked_at', 'source']);
    }
}
