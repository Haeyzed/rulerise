<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AdminService;
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
     * Admin service instance
     *
     * @var AdminService
     */
    protected AdminService $adminService;

    /**
     * Create a new controller instance.
     *
     * @param AdminService $adminService
     * @return void
     */
    public function __construct(AdminService $adminService)
    {
        $this->adminService = $adminService;
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
        $query = Candidate::with('user');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->whereHas('user', function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_featured')) {
            $isFeatured = $request->input('is_featured');
            $query->where('is_featured', $isFeatured === 'true');
        }

        if ($request->has('is_verified')) {
            $isVerified = $request->input('is_verified');
            $query->where('is_verified', $isVerified === 'true');
        }

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $candidates = $query->paginate($perPage);

        return response()->paginatedSuccess($candidates, 'Candidates retrieved successfully');
    }

    /**
     * Get candidate details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $candidate = Candidate::with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
            'resumes',
            'jobApplications',
        ])->findOrFail($id);

        return response()->success($candidate, 'Candidate details retrieved successfully.');
    }

    /**
     * Delete candidate
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $candidate = Candidate::query()->findOrFail($id);
        $user = $candidate->user;

        // Soft delete the user
        $user->delete();

        return response()->success($candidate, 'Candidate deleted successfully.');
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
        $candidate = Candidate::query()->findOrFail($id);
        $user = $candidate->user;

        $isActive = $request->input('is_active', true);

        $user = $this->adminService->moderateAccountStatus($user, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';

        return response()->success($user,"Candidate account {$status} successfully");
    }

    /**
     * Set shadow ban status
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function setShadowBan(int $id, Request $request): JsonResponse
    {
        $candidate = Candidate::query()->findOrFail($id);
        $user = $candidate->user;

        $isShadowBanned = $request->input('is_shadow_banned', false);

        $user = $this->adminService->setShadowBan($user, $isShadowBanned);

        $status = $isShadowBanned ? 'shadow banned' : 'removed from shadow ban';

        return response()->success($user,"Candidate account {$status} successfully");
    }
}
