<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\EducationHistoryRequest;
use App\Models\EducationHistory;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing candidate education histories
 */
class EducationHistoriesController extends Controller
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

        return response()->json([
            'success' => true,
            'message' => 'Education history added successfully',
            'data' => $educationHistory,
        ], 201);
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

        $educationHistory = EducationHistory::findOrFail($data['id']);

        // Check if the education history belongs to the authenticated user
        if ($educationHistory->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
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
    public function delete($id): JsonResponse
    {
        $user = auth()->user();
        $educationHistory = EducationHistory::findOrFail($id);

        // Check if the education history belongs to the authenticated user
        if ($educationHistory->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->candidateService->deleteEducationHistory($educationHistory);

        return response()->json([
            'success' => true,
            'message' => 'Education history deleted successfully',
        ]);
    }
}
