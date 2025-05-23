<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\JobApplicationResource;
use App\Http\Resources\UserResource;
use App\Services\AdminAclService;
use App\Services\AdminCandidateService;
use Exception;
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
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param AdminCandidateService $candidateService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(AdminCandidateService $candidateService, AdminAclService $adminAclService)
    {
        $this->candidateService = $candidateService;
        $this->adminAclService = $adminAclService;
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $filters = $request->all();
            $candidates = $this->candidateService->getCandidates($filters);

            return response()->paginatedSuccess(
                $candidates,
                'Candidates retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get candidate profile details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getProfileDetails(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $this->candidateService->getCandidateProfile($id);

            return response()->success([
                'candidate' => $data['candidate'],
                'statistics' => $data['statistics'],
            ], 'Candidate profile details retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $filters = $request->all();
            $applications = $this->candidateService->getCandidateApplications($id, $filters);

            return response()->paginatedSuccess(
                JobApplicationResource::collection($applications),
                'Candidate applications retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Show candidate details (legacy method)
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $data = $this->candidateService->getCandidateProfile($id);
            $candidate = $data['candidate'];

            return response()->success(
                new CandidateResource($candidate),
                'Candidate retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete candidate
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->candidateService->deleteCandidate($id);

            return response()->success('Candidate deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('moderate');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $isActive = $request->input('is_active', true);
            $user = $this->candidateService->moderateCandidateAccountStatus($id, $isActive);

            $status = $isActive ? 'activated' : 'deactivated';
            return response()->success(
                new UserResource($user),
                "Candidate account {$status} successfully"
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('moderate');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $isShadowBanned = $request->input('is_shadow_banned', false);
            $user = $this->candidateService->setShadowBanForCandidate($id, $isShadowBanned);

            $status = $isShadowBanned ? 'shadow banned' : 'removed from shadow ban';
            return response()->success(
                new UserResource($user),
                "Candidate account {$status} successfully"
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
