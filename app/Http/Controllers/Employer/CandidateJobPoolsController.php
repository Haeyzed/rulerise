<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CandidatePoolRequest;
use App\Http\Requests\Employer\AttachCandidatePoolRequest;
use App\Models\Candidate;
use App\Models\CandidatePool;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller for managing candidate pools
 */
class CandidateJobPoolsController extends Controller
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
        $this->middleware('auth:api');
        $this->middleware('role:employer');
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

        return response()->json([
            'success' => true,
            'data' => $pools,
        ]);
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

            return response()->json([
                'success' => true,
                'message' => 'Candidate pool created successfully',
                'data' => $pool,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * View candidates in a pool
     *
     * @param int $id
     * @param Request $request
     * @return JsonResponse
     */
    public function viewCandidate($id, Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $pool = $employer->candidatePools()->findOrFail($id);

        $query = $pool->candidates()->with(['user']);

        // Sort
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->input('per_page', 10);
        $candidates = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'pool' => $pool,
                'candidates' => $candidates,
            ],
        ]);
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
        $candidate = Candidate::findOrFail($data['candidate_id']);

        try {
            $this->employerService->addCandidateToPool(
                $pool,
                $candidate,
                $data['notes'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => 'Candidate added to pool successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
