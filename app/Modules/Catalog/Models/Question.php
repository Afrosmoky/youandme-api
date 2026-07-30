<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasUlids;

    /**
     * DB defaults are not loaded into the model on create(), so is_locked is also
     * declared here — the deliberate two-places pattern from P1 (see CLAUDE.md).
     *
     * @var array<string, bool>
     */
    protected $attributes = [
        'is_locked' => false,
    ];

    /** @var list<string> */
    protected $fillable = [
        'body',
        'type',
        'locale',
        'category_id',
        'tags',
        'is_locked',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tags' => 'array',
        'is_locked' => 'boolean',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function newFactory(): QuestionFactory
    {
        return QuestionFactory::new();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
