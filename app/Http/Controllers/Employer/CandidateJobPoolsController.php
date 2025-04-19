<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CandidatePoolRequest;
use App\Http\Requests\Employer\AttachCandidatePoolRequest;
use App\Http\Requests\Employer\DetachCandidatePoolRequest;
use App\Http\Resources\CandidatePoolResource;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\PoolCandidateResource;
use App\Models\Candidate;
use App\Models\Pool;
use App\Services\EmployerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing candidate pools
 */
class CandidateJobPoolsController extends Controller implements HasMiddleware
{
    /**
     * Employer service instance
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
     * Get candidate pools
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $query = $employer->candidatePools()->withCount('candidates');

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $pools = $query->paginate($perPage);

        return response()->paginatedSuccess(
            CandidatePoolResource::collection($pools),
            'Candidate pools retrieved successfully'
        );
    }

    /**
     * Create a new candidate pool
     *
     * @param CandidatePoolRequest $request
     * @return JsonResponse
     */
    public function store(CandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            $pool = $this->employerService->createCandidatePool(
                $employer,
                $data['name'],
                $data['description'] ?? null
            );

            return response()->created(new CandidatePoolResource($pool), 'Candidate pool created successfully');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * View candidates in a pool
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function viewCandidate(int $id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $pool = $employer->candidatePools()->withCount('candidates')->findOrFail($id);

        $query = $pool->candidates()->with(['user']);

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $candidates = $query->paginate($perPage);

        return response()->success([
            'pool' => new CandidatePoolResource($pool),
            'candidates' => CandidateResource::collection($candidates),
        ], 'Candidates retrieved successfully');
    }

    /**
     * Attach candidate to pool
     *
     * @param AttachCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function attachCandidatePool(AttachCandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $pool = $employer->candidatePools()->findOrFail($data['pool_id']);
        $candidate = Candidate::query()->findOrFail($data['candidate_id']);

        try {
            $this->employerService->addCandidateToPool(
                $pool,
                $candidate,
                $data['notes'] ?? null
            );

            // Get the updated pool with candidate count
            $updatedPool = $employer->candidatePools()
                ->withCount('candidates')
                ->findOrFail($data['pool_id']);

            return response()->success(
                new PoolCandidateResource($updatedPool),
                'Candidate added to pool successfully'
            );
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Detach candidate from pool
     *
     * @param DetachCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function detachCandidatePool(DetachCandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $pool = $employer->candidatePools()->findOrFail($data['pool_id']);
        $candidate = Candidate::query()->findOrFail($data['candidate_id']);

        try {
            $this->employerService->removeCandidateFromPool($pool, $candidate);

            // Get the updated pool with candidate count
            $updatedPool = $employer->candidatePools()
                ->withCount('candidates')
                ->findOrFail($data['pool_id']);

            return response()->success(
                new PoolCandidateResource($updatedPool),
                'Candidate removed from pool successfully'
            );
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
