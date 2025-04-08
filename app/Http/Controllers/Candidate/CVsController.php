<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadCvRequest;
use App\Models\Resume;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing candidate CVs/resumes
 */
class CVsController extends Controller
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
     * Upload a new CV/resume
     *
     * @param UploadCvRequest $request
     * @return JsonResponse
     */
    public function uploadCv(UploadCvRequest $request): JsonResponse
    {
        $user = auth()->user();
        $candidate = $user->candidate;
        $data = $request->validated();

        $resume = $this->candidateService->uploadResume(
            $candidate,
            $request->file('file'),
            $data['title'],
            $data['is_primary'] ?? false
        );

        return response()->json([
            'success' => true,
            'message' => 'CV uploaded successfully',
            'data' => $resume,
        ], 201);
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

        return response()->json([
            'success' => true,
            'data' => $resumes,
        ]);
    }

    /**
     * Delete CV
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete($id): JsonResponse
    {
        $user = auth()->user();
        $resume = Resume::findOrFail($id);

        // Check if the resume belongs to the authenticated user
        if ($resume->candidate_id !== $user->candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $this->candidateService->deleteResume($resume);

        return response()->json([
            'success' => true,
            'message' => 'CV deleted successfully',
        ]);
    }
}
