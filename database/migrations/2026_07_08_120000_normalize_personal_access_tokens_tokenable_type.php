<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Point every existing personal access token at the stable 'user' morph
     * alias (see AuthServiceProvider::boot). Tokens minted before R1 moved User
     * out of App\Models stored the old FQCN, which Sanctum can no longer
     * resolve; tokens minted between the move and the morph map stored the new
     * FQCN. Normalise both to 'user'. Data-only, no schema change.
     */
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->whereIn('tokenable_type', ['App\Models\User', 'Youandme\Auth\Models\User'])
            ->update(['tokenable_type' => 'user']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No reverse: the FQCN is ambiguous (pre- vs post-R1) and the morph map
        // makes 'user' the canonical value going forward.
    }
};
