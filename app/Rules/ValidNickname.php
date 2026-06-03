<?php

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidNickname implements ValidationRule
{
    /**
     * Reserved nicknames that must not be claimed by users.
     *
     * @var list<string>
     */
    public const BLACKLIST = [
        'admin', 'support', 'help', 'root', 'system',
        'youandme', 'jaity', 'mod', 'moderator', 'official',
    ];

    /**
     * @param  int|null  $ignoreUserId  user id to exclude from the uniqueness
     *                                  check (so a user can keep their own nick)
     */
    public function __construct(private ?int $ignoreUserId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[a-z0-9_]{3,30}$/', $value) !== 1) {
            $fail('Nick może zawierać tylko małe litery, cyfry i podkreślnik (od 3 do 30 znaków).');

            return;
        }

        if (in_array($value, self::BLACKLIST, true)) {
            $fail('Ten nick jest zarezerwowany.');

            return;
        }

        $query = User::where('nickname', $value);

        if ($this->ignoreUserId !== null) {
            $query->where('id', '!=', $this->ignoreUserId);
        }

        if ($query->exists()) {
            $fail('Ten nick jest już zajęty.');
        }
    }
}
