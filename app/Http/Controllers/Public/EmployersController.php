<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateProfileRequest;
use App\Http\Requests\Employer\UploadLogoRequest;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for employer profile management
 */
class EmployersController extends Controller// implements HasMiddleware
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

//    /**
//     * Get the middleware that should be assigned to the controller.
//     */
//    public static function middleware(): array
//    {
//        return [
//            new Middleware(['auth:api','role:employer']),
//        ];
//    }

    /**
     * Get employer profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->employerService->getProfile($user);

        return response()->success($profile, 'Profile retrieved successfully.');
    }
}
