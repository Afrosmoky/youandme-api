<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    /** @var array<string, string> */
    protected $attributes = [
        'timezone' => 'Europe/Warsaw',
        'locale' => 'pl',
    ];

    /** @var list<string> */
    protected $fillable = [
        'email',
        'password',
        'nickname',
        'timezone',
        'locale',
        'google_id',
        'apple_id',
        'active_couple_id',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
    ];

    /**
     * Using a property (not the casts() method) so Larastan reads the cast and
     * infers email_verified_at as a Carbon instance — UserResource formats it.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
        'email_verified_at' => 'datetime',
    ];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    /**
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * @return BelongsTo<Couple, $this>
     */
    public function activeCouple(): BelongsTo
    {
        return $this->belongsTo(Couple::class, 'active_couple_id');
    }

    /**
     * @return HasMany<Couple, $this>
     */
    public function couplesAsUserA(): HasMany
    {
        return $this->hasMany(Couple::class, 'user_a_id');
    }
}
