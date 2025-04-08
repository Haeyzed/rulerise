<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\LanguageRequest;
use App\Models\Language;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing candidate languages
 */
class CandidateLanguagesController extends Controller
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
        $this->middleware('auth:api');
        $this->middleware('role:candidate');
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

        return response()->json([
            'success' => true,
            'message' => 'Language added successfully',
            'data' => $language,
        ], 201);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $language = $this->candidateService->updateLanguage($language, $data);

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully',
            'data' => $language,
        ]);
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
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->candidateService->deleteLanguage($language);

        return response()->json([
            'success' => true,
            'message' => 'Language deleted successfully',
        ]);
    }
}
