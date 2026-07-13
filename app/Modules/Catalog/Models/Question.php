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
