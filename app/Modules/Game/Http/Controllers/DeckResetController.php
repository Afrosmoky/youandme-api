<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\ResetDeckAction;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /questions/deck/reset — start the question deck over (see ResetDeckAction).
 *
 * No body and no identifier: the couple comes from the token (IDOR — canon §5).
 * 204, because there is nothing to return — the client drops its stored deck and
 * asks GET /questions/deck for a fresh one.
 */
final class DeckResetController
{
    public function store(Request $request): Response
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        ResetDeckAction::run($couple);

        return response()->noContent();
    }
}
