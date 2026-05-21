<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuestionResource;
use App\Models\Question;
use Illuminate\Http\JsonResponse;

class QuestionController extends Controller
{
    public function next(): JsonResponse
    {
        $question = Question::query()
            ->where('type', 'session')
            ->where('locale', 'pl')
            ->inRandomOrder()
            ->firstOrFail();

        return response()->json([
            'question' => new QuestionResource($question),
        ]);
    }
}
