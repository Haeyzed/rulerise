<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\AttachSingleCandidatePoolRequest;
use App\Http\Requests\Employer\CandidatePoolRequest;
use App\Http\Requests\Employer\AttachCandidatePoolRequest;
use App\Http\Requests\Employer\AttachCandidatesMultiPoolRequest;
use App\Http\Requests\Employer\DetachCandidatePoolRequest;
use App\Http\Requests\Employer\DetachCandidatesMultiPoolRequest;
use App\Http\Requests\Employer\DetachSingleCandidatePoolRequest;
use App\Http\Resources\CandidatePoolResource;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\PoolCandidateResource;
use App\Models\Candidate;
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
            new Middleware(['auth:api','role:employer,employer_staff']),
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

        $query = $pool->candidates()->with([
            'user',
            'qualification',
            'workExperiences',
            'educationHistories',
            'languages',
            'portfolio',
            'credentials',
            'savedJobs',
            'resumes',
            'reportedJobs',
            'profileViewCounts',
        ]);

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
     * @param AttachSingleCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function attachCandidatePool(AttachSingleCandidatePoolRequest $request): JsonResponse
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
     * @param DetachSingleCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function detachCandidatePool(DetachSingleCandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $pool = $employer->candidatePools()->findOrFail($data['pool_id']);
        $candidate = Candidate::query()->findOrFail($data['candidate_id']);

        try {
            $this->employerService->removeCandidateFromPool($pool, $candidate);

            return response()->success(
                null,
                'Candidate removed from pool successfully'
            );
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Attach multiple candidates to pool
     *
     * @param AttachCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function attachCandidatesPool(AttachCandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $pool = $employer->candidatePools()->findOrFail($data['pool_id']);
        $candidateIds = $data['candidate_ids'];
        $notes = $data['notes'] ?? null;

        try {
            $results = $this->employerService->addCandidatesToPool(
                $pool,
                $candidateIds,
                $notes
            );

            // Get the updated pool with candidate count
            $updatedPool = $employer->candidatePools()
                ->withCount('candidates')
                ->findOrFail($data['pool_id']);

            return response()->success([
                'pool' => new CandidatePoolResource($updatedPool),
                'results' => $results
            ], 'Candidates added to pool');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Detach multiple candidates from pool
     *
     * @param DetachCandidatePoolRequest $request
     * @return JsonResponse
     */
    public function detachCandidatesPool(DetachCandidatePoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        $pool = $employer->candidatePools()->findOrFail($data['pool_id']);
        $candidateIds = $data['candidate_ids'];

        try {
            $results = $this->employerService->removeCandidatesFromPool(
                $pool,
                $candidateIds
            );

            // Get the updated pool with candidate count
            $updatedPool = $employer->candidatePools()
                ->withCount('candidates')
                ->findOrFail($data['pool_id']);

            return response()->success([
                'pool' => new CandidatePoolResource($updatedPool),
                'results' => $results
            ], 'Candidates removed from pool');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Attach candidates to multiple pools
     *
     * @param AttachCandidatesMultiPoolRequest $request
     * @return JsonResponse
     */
    public function attachCandidatesMultiPool(AttachCandidatesMultiPoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            $results = $this->employerService->addCandidatesToMultiplePools(
                $data['pool_ids'],
                $data['candidate_ids'],
                $data['notes'] ?? null,
                $employer
            );

            // Get updated pools with candidate counts
            $updatedPools = $employer->candidatePools()
                ->whereIn('id', $data['pool_ids'])
                ->withCount('candidates')
                ->get();

            // Check if there were any failures due to candidates not having applied
            $notAppliedFailures = collect($results['failed'])
                ->filter(function ($failure) {
                    return isset($failure['reason']) &&
                        strpos($failure['reason'], 'not applied') !== false;
                })
                ->count();

            $message = 'Candidates added to multiple pools';

            if ($notAppliedFailures > 0) {
                $message = $notAppliedFailures === count($data['candidate_ids'])
                    ? 'No candidates were added. Candidates can only be added if they have applied to one of your jobs.'
                    : 'Some candidates were not added because they have not applied to any of your jobs.';
            }

            return response()->success([
                'pools' => CandidatePoolResource::collection($updatedPools),
                'results' => $results
            ], $message);
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }

    /**
     * Detach candidates from multiple pools
     *
     * @param DetachCandidatesMultiPoolRequest $request
     * @return JsonResponse
     */
    public function detachCandidatesMultiPool(DetachCandidatesMultiPoolRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            $results = $this->employerService->removeCandidatesFromMultiplePools(
                $data['pool_ids'],
                $data['candidate_ids'],
                $employer
            );

            // Get updated pools with candidate counts
            $updatedPools = $employer->candidatePools()
                ->whereIn('id', $data['pool_ids'])
                ->withCount('candidates')
                ->get();

            return response()->success([
                'pools' => CandidatePoolResource::collection($updatedPools),
                'results' => $results
            ], 'Candidates removed from multiple pools');
        } catch (Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
