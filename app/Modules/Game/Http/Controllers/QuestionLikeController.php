<?php

namespace App\Modules\Game\Http\Controllers;

use App\Modules\Game\Actions\LikeQuestionAction;
use App\Modules\Game\Actions\UnlikeQuestionAction;
use App\Modules\Game\Models\Couple;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Question likes as two REST verbs (toggle without a stateful endpoint): POST
 * hearts, DELETE un-hearts. Both return only the like state — Game data, no
 * peer-combining — so the endpoint stays in Game, not the app layer. The couple
 * comes from the token (active_couple_id), never from input. The question is
 * addressed by ulid as a plain string param and resolved inside the Action via
 * Catalog Query — no route-model binding, so Game never Eloquent-loads Question.
 */
final class QuestionLikeController
{
    public function store(Request $request, string $questionUlid): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        LikeQuestionAction::run($couple, $questionUlid);

        return response()->json(['liked' => true]);
    }

    public function destroy(Request $request, string $questionUlid): JsonResponse
    {
        $couple = Couple::findOrFail($request->user()->active_couple_id);

        UnlikeQuestionAction::run($couple, $questionUlid);

        return response()->json(['liked' => false]);
    }
}
