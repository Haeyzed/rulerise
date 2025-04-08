<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for admin candidate management
 */
class CandidatesController extends Controller
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
        $this->middleware('auth:api');
        $this->middleware('role:admin');
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
                $q->where('name', 'like', "%{$search}%")
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

        return response()->json([
            'success' => true,
            'data' => $candidates,
        ]);
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

        return response()->json([
            'success' => true,
            'data' => $candidate,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => 'Candidate deleted successfully',
        ]);
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

        return response()->json([
            'success' => true,
            'message' => "Candidate account {$status} successfully",
            'data' => $user,
        ]);
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

        return response()->json([
            'success' => true,
            'message' => "Candidate {$status} successfully",
            'data' => $user,
        ]);
    }
}
