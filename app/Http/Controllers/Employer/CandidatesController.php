<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\UserResource;
use App\Models\Candidate;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for candidate profile management
 */
class CandidatesController extends Controller implements HasMiddleware
{
    /**
     * Candidate service instance
     *
     * @var EmployerService
     */
    protected EmployerService $employerService;

    /**
     * Create a new controller instance.
     *
     * @param EmployerService $employerService
     * @return void
     */
    public function __construct(EmployerService $employerService)
    {
        $this->employerService = $employerService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api','role:employer']),
        ];
    }

    /**
     * Get employer jobs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        // Prepare filters
        //unsorted, sorted, shortlisted, offer_sent
        $filters = [];
        if ($request->has('status')) {
            $filters['status'] = $request->input('status');
        }

        // Get sort parameters
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = $request->input('per_page', config('app.pagination.per_page'));

        $result = $this->employerService->getEmployersCandidateAppliedJobs(
            $employer,
            $filters,
            $sortBy,
            $sortOrder,
            $perPage
        );

        // Extract candidates and counts from the result
        $candidates = $result['candidates'];
        $counts = $result['counts'];

        // Add pagination data
        $paginationData = [
            'current_page' => $candidates->currentPage(),
            'last_page' => $candidates->lastPage(),
            'per_page' => $candidates->perPage(),
            'total' => $candidates->total(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Candidates retrieved successfully.',
            'data' => [
                'counts' => $counts,
                'candidates' => $candidates,
                'meta' => $paginationData,
            ]
        ]);
    }

    /**
     * Get candidate profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->employerService->getProfile($user);

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

        $candidate = $this->employerService->updateProfile($user, $data);

        return response()->success([
            'user' => new UserResource($user->fresh()),
            'candidate' => new CandidateResource($candidate)
        ], 'Profile updated successfully.');
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
        $this->employerService->recordProfileView(
            $candidate,
            $request->ip(),
            $request->userAgent(),
            auth()->check() && auth()->user()->isEmployer() ? auth()->user()->employer->id : null
        );

        return response()->success(new CandidateResource($candidate), 'Profile retrieved successfully.');
    }
}
