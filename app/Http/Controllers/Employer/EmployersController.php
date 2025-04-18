<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateProfileRequest;
use App\Http\Requests\Employer\UploadLogoRequest;
use App\Http\Resources\EmployerResource;
use App\Services\EmployerService;
use Exception;
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
        try {
            $user = auth()->user();
            $data = $request->validated();
            $employer = $this->employerService->updateProfile($user, $data);
            return response()->success($employer, 'Profile updated successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to update profile: ' . $e->getMessage());
        }
    }

    /**
     * Upload company logo
     *
     * @param UploadLogoRequest $request
     * @return JsonResponse
     */
    public function uploadLogo(UploadLogoRequest $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $employer = $user->employer;
            $updatedEmployer = $this->employerService->uploadLogo($employer, $request->validated());
            return response()->success(new EmployerResource($updatedEmployer), 'Logo uploaded successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to update company logo: ' . $e->getMessage());
        }
    }

    /**
     * Delete employer account
     *
     * @return JsonResponse
     */
    public function deleteAccount(): JsonResponse
    {
        try {
            $user = auth()->user();
            $user->delete();
            return response()->success(null, 'Account deleted successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to delete account: ' . $e->getMessage());
        }
    }
}
