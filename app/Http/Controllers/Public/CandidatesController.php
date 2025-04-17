<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Candidate;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for candidate profile management
 */
class CandidatesController extends Controller// implements HasMiddleware
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

//    /**
//     * Get the middleware that should be assigned to the controller.
//     */
//    public static function middleware(): array
//    {
//        return [
//            new Middleware(['auth:api','role:candidate']),
//        ];
//    }

    /**
     * Get candidate profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->candidateService->getProfile($user);

        return response()->success(new UserResource($profile), 'Profile retrieved successfully.');
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

        return response()->success($candidate, 'Profile updated successfully.');
    }

    /**
     * Show candidate profile (public view)
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function show(int $id, Request $request): JsonResponse
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

        return response()->success($candidate, 'Profile retrieved successfully.');
    }
}
