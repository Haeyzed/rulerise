<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employer;
use App\Services\AdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for admin employer management
 */
class EmployersController extends Controller implements HasMiddleware
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
     * Get employers list
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Employer::with('user')->withCount('jobs');

        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->whereLike('company_name', "%{$search}%")
                    ->orWhereHas('user', function($q) use ($search) {
                        $q->whereLike('first_name', "%{$search}%")
                            ->orWhereLike('last_name', "%{$search}%")
                            ->orWhereLike('email', "%{$search}%");
                    });
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

        $perPage = $request->input('per_page', config('app.pagination.per_page'));
        $employers = $query->paginate($perPage);

        return response()->paginatedSuccess($employers, 'Employers retrieved successfully');
    }

    /**
     * Get employer details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $employer = Employer::with([
            'user',
            'jobs',
            'jobs.applications.candidate',
            'subscriptions.plan',
            'candidatePools',
            'notificationTemplates',
        ])
            ->withCount('jobs')
            ->findOrFail($id);

        // Add additional job statistics
        $employer->active_jobs_count = $employer->jobs()->where('is_active', true)->count();
        $employer->featured_jobs_count = $employer->jobs()->where('is_featured', true)->count();
        $employer->draft_jobs_count = $employer->jobs()->where('is_draft', true)->count();
        $employer->expired_jobs_count = $employer->jobs()
            ->where('deadline', '<', now())
            ->where('deadline', '!=', null)
            ->count();

        return response()->success($employer, 'Employer retrieved successfully');
    }

    /**
     * Delete employer
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $employer = Employer::query()->findOrFail($id);
        $user = $employer->user;

        // Soft delete the user
        $user->delete();

        return response()->success('Employer deleted successfully');
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
        $employer = Employer::query()->findOrFail($id);
        $user = $employer->user;

        $isActive = $request->input('is_active', true);

        $user = $this->adminService->moderateAccountStatus($user, $isActive);

        $status = $isActive ? 'activated' : 'deactivated';

        return response()->success($user,"Employer account {$status} successfully");
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
        $employer = Employer::query()->findOrFail($id);
        $user = $employer->user;

        $isShadowBanned = $request->input('is_shadow_banned', false);

        $user = $this->adminService->setShadowBan($user, $isShadowBanned);

        $status = $isShadowBanned ? 'shadow banned' : 'removed from shadow ban';

        return response()->success($user,"Employer account {$status} successfully");
    }
}
