<?php

namespace Youandme\Auth\Models;

use App\Models\Couple;
use App\Models\Memory;
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
use Youandme\Auth\Database\Factories\UserFactory;

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

    protected static function newFactory(): UserFactory
    {
        return UserFactory::new();
    }

    /**
     * TODO Etap 5 (Memories): cross-module relation kept during R1 migration.
     * Memory lives in App\Models until the Memories module is extracted.
     *
     * @return HasMany<Memory, $this>
     */
    public function memories(): HasMany
    {
        return $this->hasMany(Memory::class);
    }

    /**
     * TODO Etap 4 (Game): cross-module relation kept during R1 migration.
     * Couple lives in App\Models until the Game module is extracted.
     *
     * @return BelongsTo<Couple, $this>
     */
    public function activeCouple(): BelongsTo
    {
        return $this->belongsTo(Couple::class, 'active_couple_id');
    }

    /**
     * TODO Etap 4 (Game): cross-module relation kept during R1 migration.
     *
     * @return HasMany<Couple, $this>
     */
    public function couplesAsUserA(): HasMany
    {
        return $this->hasMany(Couple::class, 'user_a_id');
    }
}
