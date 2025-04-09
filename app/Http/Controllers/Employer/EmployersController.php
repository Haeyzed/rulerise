<?php

namespace App\Http\Controllers\Employer;

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
class EmployersController extends Controller implements HasMiddleware
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

    /**
     * Update employer profile
     *
     * @param UpdateProfileRequest $request
     * @return JsonResponse
     */
    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = auth()->user();
        $data = $request->validated();

        $employer = $this->employerService->updateProfile($user, $data);

        return response()->success($employer, 'Profile updated successfully');
    }

    /**
     * Upload company logo
     *
     * @param UploadLogoRequest $request
     * @return JsonResponse
     */
    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $updatedEmployer = $this->employerService->uploadLogo(
            $employer,
            $request->file('file')
        );

        return response()->success(['company_logo' => $updatedEmployer->company_logo,], 'Logo uploaded successfully');
    }

    /**
     * Delete employer account
     *
     * @return JsonResponse
     */
    public function deleteAccount(): JsonResponse
    {
        $user = auth()->user();

        // Soft delete the user
        $user->delete();

        return response()->success(null, 'Account deleted successfully');
    }
}
