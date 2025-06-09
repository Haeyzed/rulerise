<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\WorkExperienceRequest;
use App\Http\Resources\WorkExperienceResource;
use App\Models\WorkExperience;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate work experiences
 */
class WorkExperiencesController extends Controller implements HasMiddleware
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
     * Get all work experiences for the authenticated candidate
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;

        $workExperiences = $this->candidateService->getWorkExperiences($candidate);

        return response()->success(
            WorkExperienceResource::collection($workExperiences),
            'Work experiences retrieved successfully'
        );
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

        return response()->created(
            new WorkExperienceResource($workExperience),
            'Work experience added successfully'
        );
    }

    /**
     * Update work experience
     *
     * @param WorkExperienceRequest $request
     * @return JsonResponse
     */
    public function update(int $id, WorkExperienceRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $workExperience = WorkExperience::query()->findOrFail($id);

        // Check if the work experience belongs to the authenticated user
        if ($workExperience->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $workExperience = $this->candidateService->updateWorkExperience($workExperience, $data);

        return response()->success(
            new WorkExperienceResource($workExperience),
            'Work experience updated successfully'
        );
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
        $workExperience = WorkExperience::query()->findOrFail($id);

        // Check if the work experience belongs to the authenticated user
        if ($workExperience->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $this->candidateService->deleteWorkExperience($workExperience);

        return response()->success(null, 'Work experience deleted successfully');
    }
}
