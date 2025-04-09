<?php

namespace App\Http\Controllers\Candidate;

use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UploadProfilePictureRequest;
use App\Services\CandidateService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for user account settings
 */
class UserAccountSettingsController extends Controller
{
    /**
     * Candidate service instance
     *
     * @var CandidateService
     */
    protected CandidateService $candidateService;

    /**
     * Create a new controller instance.
     *
     * @param CandidateService $candidateService
     * @return void
     */
    public function __construct(CandidateService $candidateService)
    {
        $this->candidateService = $candidateService;
        $this->middleware('auth:api');
    }

    /**
     * Upload profile picture
     *
     * @param UploadProfilePictureRequest $request
     * @return JsonResponse
     */
    public function uploadProfilePicture(UploadProfilePictureRequest $request): JsonResponse
    {
        $user = auth()->user();

        $updatedUser = $this->candidateService->uploadProfilePicture(
            $user,
            $request->file('file')
        );

        return response()->success(['profile_picture' => $updatedUser->profile_picture,],'Profile picture uploaded successfully');
    }
}
