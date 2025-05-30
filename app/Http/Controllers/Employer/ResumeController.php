<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\SearchCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\DegreeResource;
use App\Http\Resources\ResumeResource;
use App\Models\Degree;
use App\Models\Resume;
use App\Services\ResumeService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Controller for resume-related operations for employers
 */
class ResumeController extends Controller implements HasMiddleware
{
    /**
     * Resume service instance
     *
     * @var ResumeService
     */
    protected ResumeService $resumeService;

    /**
     * Create a new controller instance.
     *
     * @param ResumeService $resumeService
     * @return void
     */
    public function __construct(ResumeService $resumeService)
    {
        $this->resumeService = $resumeService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:employer']),
        ];
    }

    /**
     * Search candidates
     *
     * @param SearchCandidateRequest $request
     * @return JsonResponse
     */
    public function searchCandidates(SearchCandidateRequest $request): JsonResponse
    {
        try {
            $employer = auth()->user()->isEmployer()
                ? auth()->user()->employer
                : auth()->user()->employerRelation;

            if (!$employer) {
                return response()->notFound('Employer profile not found');
            }

            // Get all request parameters as filters
            $filters = $request->validated();
            $perPage = $request->input('per_page', config('app.pagination.per_page'));

            $candidates = $this->resumeService->searchCandidates($employer, $filters, $perPage);

            return response()->paginatedSuccess(
                CandidateResource::collection($candidates),
                'Candidates retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    /**
     * Get candidate details
     *
     * @param int $id
     * @return JsonResponse
     */
    public function showCandidate(int $id): JsonResponse
    {
        try {
            $employer = auth()->user()->isEmployer()
                ? auth()->user()->employer
                : auth()->user()->employerRelation;

            if (!$employer) {
                return response()->error('Employer profile not found', 404);
            }

            $candidate = $this->resumeService->getCandidateDetails($id, $employer);

            return response()->success(
                new CandidateResource($candidate),
                'Candidate details retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    /**
     * Download a resume
     *
     * @param int $id
     * @return JsonResponse|BinaryFileResponse
     */
    public function downloadResume(int $id): JsonResponse|BinaryFileResponse
    {
        try {
            $employer = auth()->user()->isEmployer()
                ? auth()->user()->employer
                : auth()->user()->employerRelation;

            if (!$employer) {
                return response()->error('Employer profile not found', 404);
            }

            $resume = $this->resumeService->downloadResume($id, $employer);

            // Get the file path from storage
            $filePath = storage_path('app/' . $resume->document);

            if (!file_exists($filePath)) {
                return response()->error('Resume file not found', 404);
            }

            // Generate a filename for download
            $candidateName = $resume->candidate->user->full_name ?? 'Candidate';
            $filename = str_replace(' ', '_', $candidateName) . '_Resume.pdf';

            return response()->download($filePath, $filename);
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    /**
     * Get recommended candidates
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function recommendedCandidates(Request $request): JsonResponse
    {
        try {
            $employer = auth()->user()->isEmployer()
                ? auth()->user()->employer
                : auth()->user()->employerRelation;

            if (!$employer) {
                return response()->error('Employer profile not found', 404);
            }

            $perPage = $request->input('per_page', config('app.pagination.per_page'));

            $candidates = $this->resumeService->getRecommendedCandidates($employer, $perPage);

            return response()->paginatedSuccess(
                CandidateResource::collection($candidates),
                'Recommended candidates retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->error($e->getMessage(), 403);
        }
    }

    /**
     * Get degrees for dropdown
     *
     * @return JsonResponse
     */
    public function getDegrees(): JsonResponse
    {
        try {
            $degrees = Degree::orderBy('level')->get();
            return response()->success(
                DegreeResource::collection($degrees),
                'Degrees retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve degrees', $e->getMessage());
        }
    }

    /**
     * Get subscription usage statistics
     *
     * @return JsonResponse
     */
    public function getSubscriptionUsage(): JsonResponse
    {
        try {
            $employer = auth()->user()->isEmployer()
                ? auth()->user()->employer
                : auth()->user()->employerRelation;

            if (!$employer) {
                return response()->notFound('Employer profile not found');
            }

            $usage = $this->resumeService->getSubscriptionUsage($employer);

            return response()->success($usage, 'Subscription usage retrieved successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to retrieve subscription usage', $e->getMessage());
        }
    }
}
