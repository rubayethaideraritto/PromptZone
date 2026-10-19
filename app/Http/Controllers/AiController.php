<?php

namespace App\Http\Controllers;

use App\Exceptions\AiException;
use App\Http\Requests\AiRequest;
use App\Services\AiPromptService;
use Illuminate\Http\JsonResponse;

class AiController extends Controller
{
    public function __construct(protected readonly AiPromptService $ai) {}

    /**
     * Review the submitted prompt text.
     */
    public function analyze(AiRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->ai->analyze(
            (string) $request->validated('prompt_text'),
            $request->validated('purpose')
        ));
    }

    /**
     * Produce a rewritten draft of the submitted prompt text.
     */
    public function improve(AiRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->ai->improve(
            (string) $request->validated('prompt_text')
        ));
    }

    /**
     * Suggest a category and tags for the submitted prompt text.
     */
    public function suggest(AiRequest $request): JsonResponse
    {
        return $this->respond(fn (): array => $this->ai->suggestMeta(
            (string) $request->validated('prompt_text')
        ));
    }

    /**
     * Run an AI action and map the outcome onto JSON.
     *
     * Nothing here writes to the database and nothing here invents content: a
     * failure returns success=false with a readable reason, and the caller's
     * draft is left exactly as the user typed it.
     *
     * @param  callable(): array<string, mixed>  $action
     */
    protected function respond(callable $action): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => $action(),
            ]);
        } catch (AiException $exception) {
            // The message is already user-safe; the verbose version is in the log.
            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
            ], 502);
        }
    }
}
