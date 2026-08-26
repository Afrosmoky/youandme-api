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
        // Identity in the seed file (q001/d001…). Fillable so the seeders can key
        // on it; null for anything not born of a seed file.
        'seed_key',
        'body',
        'type',
        'locale',
        'category_id',
        'tags',
        'is_locked',
        'options',
    ];

    /**
     * options is NOT in $attributes: its default is NULL (an open question), not
     * a DB DEFAULT, so there is nothing for create() to fail to load — the
     * two-places pattern from P1 does not apply here.
     *
     * Shape when present: {items: string[], multiple: bool}. See the S2 migration
     * for why the flag rides inside the envelope.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'tags' => 'array',
        'is_locked' => 'boolean',
        'options' => 'array',
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
