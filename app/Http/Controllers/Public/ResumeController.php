<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\SearchCandidateRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\DegreeResource;
use App\Models\Degree;
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
class ResumeController extends Controller
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
}
