<?php

namespace App\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'body',
        'type',
        'locale',
        'category_id',
        'tags',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tags' => 'array',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Couple, $this>
     */
    public function seenByCouples(): BelongsToMany
    {
        return $this->belongsToMany(Couple::class, 'couple_question_seen')
            ->withPivot('seen_at');
    }
}
