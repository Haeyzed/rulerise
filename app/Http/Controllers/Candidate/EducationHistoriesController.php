<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\EducationHistoryRequest;
use App\Models\EducationHistory;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate education histories
 */
class EducationHistoriesController extends Controller implements HasMiddleware
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
     * Store a new education history
     *
     * @param EducationHistoryRequest $request
     * @return JsonResponse
     */
    public function store(EducationHistoryRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $data = $request->validated();

        $educationHistory = $this->candidateService->addEducationHistory($candidate, $data);

        return response()->created($educationHistory, 'Education history added successfully');
    }

    /**
     * Update education history
     *
     * @param EducationHistoryRequest $request
     * @return JsonResponse
     */
    public function update(EducationHistoryRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $educationHistory = EducationHistory::query()->findOrFail($data['id']);

        // Check if the education history belongs to the authenticated user
        if ($educationHistory->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $educationHistory = $this->candidateService->updateEducationHistory($educationHistory, $data);

        return response()->json([
            'success' => true,
            'message' => 'Education history updated successfully',
            'data' => $educationHistory,
        ]);
    }

    /**
     * Delete education history
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $educationHistory = EducationHistory::query()->findOrFail($id);

        // Check if the education history belongs to the authenticated user
        if ($educationHistory->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $this->candidateService->deleteEducationHistory($educationHistory);

        return response()->success('Education history deleted successfully');
    }
}
