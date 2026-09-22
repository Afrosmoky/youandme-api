<?php

namespace App\Console\Commands;

use App\Actions\DeleteAccountAction;
use App\Exceptions\AccountDeletionRefusedException;
use Illuminate\Console\Command;
use Youandme\Auth\Models\User;

/**
 * Ops command for deletion requests that arrive by e-mail (the promise on
 * /delete-account for people without the app). Runs the very same
 * DeleteAccountAction as DELETE /me, so the two paths cannot drift.
 *
 * The e-mail is a required argument and the deletion is confirmed interactively
 * after the account is shown. Under --no-interaction the confirmation answers no,
 * so the command cannot be scripted into deleting anything by accident.
 *
 * There is no Apple authorization code on this path, so an Apple-linked account
 * is deleted without revoking our Apple tokens (a warning is logged); the person
 * can remove the app from their Apple ID settings themselves.
 */
class DeleteAccountCommand extends Command
{
    protected $signature = 'account:delete {email : The e-mail address of the account to delete}';

    protected $description = 'Permanently delete an account and all of its data (deletion requests sent by e-mail).';

    public function handle(): int
    {
        $email = trim((string) $this->argument('email'));

        // citext: the match is case-insensitive, like sign-in.
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error('No account with this e-mail address.');

            return self::FAILURE;
        }

        $this->table(['ulid', 'nickname', 'created', 'Apple', 'Google'], [[
            $user->ulid,
            $user->nickname,
            $user->created_at->toIso8601ZuluString(),
            $user->apple_id !== null ? 'yes' : 'no',
            $user->google_id !== null ? 'yes' : 'no',
        ]]);

        if ($user->apple_id !== null) {
            $this->warn('Apple-linked account: its Apple tokens cannot be revoked from here (no authorization code).');
        }

        if (! $this->confirm('Delete this account and all of its data permanently? This cannot be undone.', false)) {
            $this->info('Nothing deleted.');

            return self::FAILURE;
        }

        try {
            DeleteAccountAction::run($user);
        } catch (AccountDeletionRefusedException) {
            $this->error('The account is in a couple with another member; deleting it needs a decision about the shared history first.');

            return self::FAILURE;
        }

        $this->info("Account {$user->ulid} deleted.");

        return self::SUCCESS;
    }
}
