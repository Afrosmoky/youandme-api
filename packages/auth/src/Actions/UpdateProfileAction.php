<?php

namespace Youandme\Auth\Actions;

use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;
use Youandme\Auth\Models\User;

final class UpdateProfileAction
{
    use AsAction;

    /**
     * Update the Auth-owned profile fields (nickname, timezone, locale).
     * `partner_name_local` belongs to Game (Couple) and is orchestrated by the
     * controller in the same transaction — see ProfileController::update.
     *
     * @param  array<string, mixed>  $validated  the full validated PATCH /me payload
     */
    public function handle(User $user, array $validated): void
    {
        $user->fill(Arr::except($validated, ['partner_name_local']));
        $user->save();
    }
}
