<?php

use App\Modules\Game\Models\Couple;
use Youandme\Auth\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Give every pre-existing user the couple that registration now creates
     * automatically. Atomic so we never leave a user without a couple.
     */
    public function up(): void
    {
        DB::transaction(function () {
            User::query()->chunkById(100, function ($users) {
                foreach ($users as $user) {
                    if ($user->active_couple_id !== null) {
                        continue;
                    }

                    $couple = Couple::create(['user_a_id' => $user->id]);
                    $user->active_couple_id = $couple->id;
                    $user->save();
                }
            });
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse: the couples table is dropped by its own migration.
    }
};
