<?php

namespace App\Modules\Catalog\Models;

use Database\Factories\RitualFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A weekly ritual — Catalog content (aggregate root), separate from Question.
 * Title + instruction, no answer/category/tags. Timeless: it knows nothing about
 * weeks; the week is attached when the ritual is assigned to a couple.
 */
class Ritual extends Model
{
    /** @use HasFactory<RitualFactory> */
    use HasFactory, HasUlids;

    /** @var list<string> */
    protected $fillable = [
        'title',
        'body',
        'locale',
        'ordering',
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
        'ordering' => 'integer',
    ];

    protected static function newFactory(): RitualFactory
    {
        return RitualFactory::new();
    }
}
