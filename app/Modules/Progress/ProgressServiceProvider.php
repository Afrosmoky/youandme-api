<?php

namespace App\Modules\Progress;

use Illuminate\Support\ServiceProvider;

/**
 * Progress owns the milestone dictionary and the record of which couples reached
 * which stage — the gamification layer of the app.
 *
 * It registers NO routes, like Premium: GET /progress needs the card counter from
 * Memories as well, so the composition (and the endpoint) belongs to the app
 * layer. That is what lets a whole new module arrive without adding an edge to
 * the graph — Progress → ∅.
 */
class ProgressServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }
}
