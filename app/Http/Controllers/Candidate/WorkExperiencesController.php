<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\WorkExperienceRequest;
use App\Models\WorkExperience;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing candidate work experiences
 */
class WorkExperiencesController extends Controller
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
     * Store a new work experience
     *
     * @param WorkExperienceRequest $request
     * @return JsonResponse
     */
    public function store(WorkExperienceRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $data = $request->validated();

        $workExperience = $this->candidateService->addWorkExperience($candidate, $data);

        return response()->json([
            'success' => true,
            'message' => 'Work experience added successfully',
            'data' => $workExperience,
        ], 201);
    }

    /**
     * Update work experience
     *
     * @param WorkExperienceRequest $request
     * @return JsonResponse
     */
    public function update(WorkExperienceRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $workExperience = WorkExperience::findOrFail($data['id']);

        // Check if the work experience belongs to the authenticated user
        if ($workExperience->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $workExperience = $this->candidateService->updateWorkExperience($workExperience, $data);

        return response()->json([
            'success' => true,
            'message' => 'Work experience updated successfully',
            'data' => $workExperience,
        ]);
    }

    /**
     * Delete work experience
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $workExperience = WorkExperience::findOrFail($id);

        // Check if the work experience belongs to the authenticated user
        if ($workExperience->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->candidateService->deleteWorkExperience($workExperience);

        return response()->json([
            'success' => true,
            'message' => 'Work experience deleted successfully',
        ]);
    }
}
