<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Models\Candidate;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for candidate profile management
 */
class CandidatesController extends Controller
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
     * Get candidate profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->candidateService->getProfile($user);

        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
    }

    /**
     * Update candidate profile
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $candidate = $this->candidateService->updateProfile($user, $data);

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $candidate,
        ]);
    }

    /**
     * Show candidate profile (public view)
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show($id, Request $request): JsonResponse
    {
        $candidate = Candidate::with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
        ])->findOrFail($id);

        // Record profile view
        $this->candidateService->recordProfileView(
            $candidate,
            $request->ip(),
            $request->userAgent(),
            auth()->check() && auth()->user()->isEmployer() ? auth()->user()->employer->id : null
        );

        return response()->json([
            'success' => true,
            'data' => $candidate,
        ]);
    }
}
