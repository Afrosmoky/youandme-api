<?php

namespace Youandme\Auth\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\HasApiTokens;
use Youandme\Auth\Database\Factories\UserFactory;
use Youandme\Auth\Events\EmailVerificationRequested;
use Youandme\Auth\Events\PasswordResetRequested;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlids, MustVerifyEmailTrait, Notifiable, SoftDeletes;

    /** @var array<string, string> */
    protected $attributes = [
        'timezone' => 'Europe/Warsaw',
        'locale' => 'pl',
    ];

    /**
     * active_couple_id is an Auth-owned column pointing at a Game couple id (a
     * cross-module FK, DR-009). Auth stores it (via SetActiveCoupleForUserAction)
     * but has no couple relation — resolving the couple is Game's job.
     *
     * @var list<string>
     */
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
     * Instead of sending the framework's VerifyEmail notification, build the
     * signed verification URL and emit a domain event. Notifications listens and
     * sends the mail — Auth stays decoupled from Notifications (event-driven,
     * R1 Etap 3). Same URL shape as the built-in notification.
     */
    public function sendEmailVerificationNotification(): void
    {
        $url = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes((int) config('auth.verification.expire', 60)),
            ['id' => $this->getKey(), 'hash' => sha1($this->getEmailForVerification())],
        );

        EmailVerificationRequested::dispatch($this->ulid, $this->getEmailForVerification(), $url);
    }

    /**
     * Instead of sending the framework's ResetPassword notification, build the
     * (deep-link) reset URL and emit a domain event for Notifications to send.
     * Still driven by Password::sendResetLink, so the broker throttle and token
     * creation are unchanged.
     *
     * @param  string  $token
     */
    public function sendPasswordResetNotification($token): void
    {
        $email = $this->getEmailForPasswordReset();
        $query = 'reset-password?token='.$token.'&email='.urlencode($email);

        $scheme = config('app.mobile_deep_link_scheme');
        $url = $scheme
            ? $scheme.'://'.$query
            : config('app.url').'/'.$query;

        PasswordResetRequested::dispatch($email, $url);
    }
}
