<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\UserResource;
use App\Services\AdminCandidateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin candidate management
 */
class CandidatesController extends Controller implements HasMiddleware
{
    /**
     * Admin candidate service instance
     *
     * @var AdminCandidateService
     */
    protected AdminCandidateService $candidateService;

    /**
     * Create a new controller instance.
     *
     * @param AdminCandidateService $candidateService
     * @return void
     */
    public function __construct(AdminCandidateService $candidateService)
    {
        $this->candidateService = $candidateService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:admin']),
        ];
    }

    /**
     * Get candidates list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->all();
        $candidates = $this->candidateService->getCandidates($filters);

        return response()->paginatedSuccess(
            CandidateResource::collection($candidates),
            'Candidates retrieved successfully'
        );
    }

    /**
     * Get candidate profile details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getProfileDetails(int $id): JsonResponse
    {
        $data = $this->candidateService->getCandidateProfile($id);

        return response()->success([
            'candidate' => new CandidateResource($data['candidate']),
            'statistics' => $data['statistics'],
        ], 'Candidate profile details retrieved successfully');
    }

    /**
     * Get candidate applications
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function getApplications(int $id, Request $request): JsonResponse
    {
        $filters = $request->all();
        $applications = $this->candidateService->getCandidateApplications($id, $filters);

        return response()->paginatedSuccess(
            JobApplicationResource::collection($applications),
            'Candidate applications retrieved successfully'
        );
    }

    /**
     * Show candidate details (legacy method)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $data = $this->candidateService->getCandidateProfile($id);
        $candidate = $data['candidate'];

        return response()->success(
            new CandidateResource($candidate),
            'Candidate retrieved successfully'
        );
    }

    /**
     * Delete candidate
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $this->candidateService->deleteCandidate($id);

        return response()->success('Candidate deleted successfully');
    }

    /**
     * Moderate account status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function moderateAccountStatus(int $id, Request $request): JsonResponse
    {
        $isActive = $request->input('is_active', true);
        $user = $this->candidateService->moderateCandidateAccountStatus($id, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';
        return response()->success(
            new UserResource($user),
            "Candidate account {$status} successfully"
        );
    }

    /**
     * Set shadow-ban status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setShadowBan(int $id, Request $request): JsonResponse
    {
        $isShadowBanned = $request->input('is_shadow_banned', false);
        $user = $this->candidateService->setShadowBanForCandidate($id, $isShadowBanned);

        $status = $isShadowBanned ? 'shadow banned' : 'removed from shadow ban';
        return response()->success(
            new UserResource($user),
            "Candidate account {$status} successfully"
        );
    }
}
