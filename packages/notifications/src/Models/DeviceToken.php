<?php

namespace Youandme\Notifications\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One device a user can be pushed to. Keyed to the user by ulid held as a plain
 * value — the package knows nothing about Auth, its User model, or couples.
 */
class DeviceToken extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'user_ulid',
        'token',
        'platform',
    ];
}
