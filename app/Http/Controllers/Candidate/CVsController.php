<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadCvRequest;
use App\Http\Resources\ResumeResource;
use App\Models\Resume;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate CVs/resumes
 */
class CVsController extends Controller implements HasMiddleware
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
     * Upload a new CV/resume
     *
     * @param UploadCvRequest $request
     * @return JsonResponse
     */
    public function uploadCv(UploadCvRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $resume = $this->candidateService->uploadResume($candidate, $request->validated());

        return response()->created(new ResumeResource($resume), 'CV uploaded successfully');
    }

    /**
     * Get CV details
     *
     * @return JsonResponse
     */
    public function cvDetail(): JsonResponse
    {
        $user = auth()->user();
        $resumes = $user->candidate->resumes;

        return response()->success(ResumeResource::collection($resumes), 'CV detail retrieved successfully');
    }

    /**
     * Delete CV
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $resume = Resume::query()->findOrFail($id);

        // Check if the resume belongs to the authenticated user
        if ($resume->candidate_id !== $user->candidate->id) {
            return response()->forbidden('Unauthorized');
        }

        $this->candidateService->deleteResume($resume);

        return response()->success(null, 'CV deleted successfully');
    }
}
