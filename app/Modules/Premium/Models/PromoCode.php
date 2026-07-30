<?php

namespace App\Modules\Premium\Models;

use App\Modules\Premium\Support\PromoCodeKind;
use Database\Factories\PromoCodeFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A promo code (Premium aggregate root): what it grants, how many times it may be
 * used and until when.
 *
 * The code column is citext, so lookups are case-insensitive without lowercasing
 * anything in PHP — people type these by hand off a card or a post.
 */
class PromoCode extends Model
{
    /** @use HasFactory<PromoCodeFactory> */
    use HasFactory, HasUlids;

    /**
     * DB defaults are not loaded into the model on create() — the deliberate
     * two-places pattern from P1 (see CLAUDE.md).
     *
     * @var array<string, int>
     */
    protected $attributes = [
        'used_count' => 0,
    ];

    /** @var list<string> */
    protected $fillable = [
        'code',
        'kind',
        'max_uses',
        'expires_at',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'kind' => PromoCodeKind::class,
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    protected static function newFactory(): PromoCodeFactory
    {
        return PromoCodeFactory::new();
    }
}
