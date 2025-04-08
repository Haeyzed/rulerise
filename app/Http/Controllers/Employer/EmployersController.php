<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateProfileRequest;
use App\Http\Requests\Employer\UploadLogoRequest;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for employer profile management
 */
class EmployersController extends Controller
{
    /**
     * Employer service instance
     *
     * @var EmployerService
     */
    protected $employerService;

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
     * Get employer profile
     *
     * @return JsonResponse
     */
    public function getProfile(): JsonResponse
    {
        $user = auth()->user();
        $profile = $this->employerService->getProfile($user);
        
        return response()->json([
            'success' => true,
            'data' => $profile,
        ]);
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
        
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $employer,
        ]);
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
        
        return response()->json([
            'success' => true,
            'message' => 'Logo uploaded successfully',
            'data' => [
                'company_logo' => $updatedEmployer->company_logo,
            ],
        ]);
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
        
        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully',
        ]);
    }
}