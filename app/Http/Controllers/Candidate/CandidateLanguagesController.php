<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\LanguageRequest;
use App\Models\Language;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate languages
 */
class CandidateLanguagesController extends Controller implements HasMiddleware
{
    /**
     * Candidate service instance
     *
     * @var CandidateService
     */
    protected CandidateService $candidateService;

    /**
     * Create a new controller instance.
     *
     * @param CandidateService $candidateService
     * @return void
     */
    public function __construct(CandidateService $candidateService)
    {
        $this->candidateService = $candidateService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:candidate']),
        ];
    }

    /**
     * Store a new language
     *
     * @param LanguageRequest $request
     * @return JsonResponse
     */
    public function store(LanguageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $data = $request->validated();

        $language = $this->candidateService->addLanguage($candidate, $data);

        return response()->created($language,'Language created successfully');
    }

    /**
     * Update language
     *
     * @param LanguageRequest $request
     * @return JsonResponse
     */
    public function update(LanguageRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $language = Language::query()->findOrFail($data['id']);

        // Check if the language belongs to the authenticated user
        if ($language->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $language = $this->candidateService->updateLanguage($language, $data);

        return response()->success($language,'Language updated successfully');
    }

    /**
     * Delete language
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $language = Language::query()->findOrFail($id);

        // Check if the language belongs to the authenticated user
        if ($language->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $this->candidateService->deleteLanguage($language);

        return response()->success($language,'Language deleted successfully');
    }
}
