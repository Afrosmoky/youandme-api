<?php

use App\Actions\DeleteAccountAction;
use Illuminate\Support\Facades\DB;
use Youandme\Auth\Models\User;

/**
 * Guards account deletion against the schema it will meet in half a year: every
 * table that can reference a user or a couple must end up with no row of a
 * deleted account. A new table that nobody taught DeleteAccountAction about fails
 * here instead of quietly keeping data of people who asked us to erase it.
 *
 * Two nets, because a name alone would miss an owner_id: columns by the names we
 * use for these references, and every foreign key pointing at users or couples.
 *
 * The fixture must reach each discovered column first — an empty table would
 * pass the "nothing left" check without proving anything, so "not seeded" is a
 * failure too, telling you to extend seedAccountFootprint.
 */

/** Column names that hold a reference to a user or a couple, by what they hold. */
const ACCOUNT_REFERENCE_COLUMNS = [
    'user_id' => 'user_id',
    'user_a_id' => 'user_id',
    'user_b_id' => 'user_id',
    'referrer_user_id' => 'user_id',
    'referred_user_id' => 'user_id',
    'tokenable_id' => 'user_id',
    'user_ulid' => 'user_ulid',
    'couple_id' => 'couple_id',
    'active_couple_id' => 'couple_id',
    'email' => 'email',
];

/**
 * Columns deliberately not seeded, with the reason. Keep this list short.
 *
 * couples.user_b_id: a user in a shared couple is refused (409, see
 * DeleteAccountTest), so a deletable account never has one.
 */
const ACCOUNT_REFERENCES_NOT_SEEDED = ['couples.user_b_id'];

/**
 * @return array<string, string> "table.column" => what it holds
 */
function accountReferenceColumns(): array
{
    $found = [];

    $columns = DB::select(
        "select table_name, column_name from information_schema.columns
         where table_schema = 'public' and column_name = any(?)",
        ['{'.implode(',', array_keys(ACCOUNT_REFERENCE_COLUMNS)).'}'],
    );
    foreach ($columns as $column) {
        $found["{$column->table_name}.{$column->column_name}"] = ACCOUNT_REFERENCE_COLUMNS[$column->column_name];
    }

    $foreignKeys = DB::select(
        "select kcu.table_name, kcu.column_name, ccu.table_name as target
         from information_schema.table_constraints tc
         join information_schema.key_column_usage kcu on kcu.constraint_name = tc.constraint_name
         join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name
         where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'
           and ccu.table_name in ('users', 'couples')",
    );
    foreach ($foreignKeys as $key) {
        $found["{$key->table_name}.{$key->column_name}"] = $key->target === 'users' ? 'user_id' : 'couple_id';
    }

    ksort($found);

    return $found;
}

/**
 * @param  array{user_id: int, user_ulid: string, couple_id: int, email: string}  $account
 */
function countAccountRows(string $reference, string $kind, array $account): int
{
    [$table, $column] = explode('.', $reference);

    return DB::table($table)->where($column, $account[$kind])->count();
}

/** @return array{user_id: int, user_ulid: string, couple_id: int, email: string} */
function accountKeys(User $user): array
{
    return [
        'user_id' => $user->id,
        'user_ulid' => $user->ulid,
        'couple_id' => (int) $user->active_couple_id,
        'email' => $user->email,
    ];
}

test('the guard sees the references it is meant to see', function (): void {
    expect(accountReferenceColumns())->toHaveKeys([
        'memories.couple_id', 'memories.user_id', 'device_tokens.user_ulid',
        'referrals.referrer_user_id', 'referrals.referred_user_id', 'users.active_couple_id',
        'personal_access_tokens.tokenable_id', 'password_reset_tokens.email',
    ]);
});

test('deleting an account leaves no row of the user or their couple in any table', function (): void {
    $user = createUserWithCouple();
    seedAccountFootprint($user);
    $account = accountKeys($user->fresh());

    $control = createUserWithCouple();
    seedAccountFootprint($control);
    $controlAccount = accountKeys($control->fresh());

    $references = array_diff_key(accountReferenceColumns(), array_flip(ACCOUNT_REFERENCES_NOT_SEEDED));
    $controlBefore = [];

    foreach ($references as $reference => $kind) {
        expect(countAccountRows($reference, $kind, $account))
            ->toBeGreaterThan(0, "{$reference} is not seeded — extend seedAccountFootprint() and make sure DeleteAccountAction clears it");
        $controlBefore[$reference] = countAccountRows($reference, $kind, $controlAccount);
    }

    DeleteAccountAction::run($user);

    foreach ($references as $reference => $kind) {
        expect(countAccountRows($reference, $kind, $account))
            ->toBe(0, "{$reference} still holds data of the deleted account")
            ->and(countAccountRows($reference, $kind, $controlAccount))
            ->toBe($controlBefore[$reference], "{$reference} lost data of an unrelated account");
    }
});
