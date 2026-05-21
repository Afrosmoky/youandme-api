<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\MemoryCreated;
use App\Events\QuestionAnswered;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Memories\StoreMemoryRequest;
use App\Http\Resources\MemoryResource;
use App\Models\Question;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MemoryController extends Controller
{
    public function store(StoreMemoryRequest $request): JsonResponse
    {
        $question = Question::where('ulid', $request->string('question_ulid'))->firstOrFail();

        $memory = $request->user()->memories()->create([
            'question_id' => $question->id,
            'answer' => $request->string('answer'),
            'answered_at' => $request->date('answered_at'),
        ]);

        $memory->setRelation('question', $question);

        QuestionAnswered::dispatch($memory);
        MemoryCreated::dispatch($memory);

        return response()->json([
            'memory' => new MemoryResource($memory),
        ], Response::HTTP_CREATED);
    }

    public function index(Request $request): JsonResponse
    {
        $memories = $request->user()->memories()
            ->with('question')
            ->orderByDesc('answered_at')
            ->limit(50)
            ->get();

        return response()->json([
            'memories' => MemoryResource::collection($memories),
        ]);
    }
}
