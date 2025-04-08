<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;

/**
 * Controller for managing subscriptions
 */
class SubscriptionsController extends Controller
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
     * Get subscription information
     *
     * @return JsonResponse
     */
    public function subscriptionInformation(): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        $subscription = $employer->activeSubscription()->with('plan')->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription,
        ]);
    }

    /**
     * Update CV download usage
     *
     * @return JsonResponse
     */
    public function updateCVDownloadUsage(): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        try {
            $this->employerService->updateCvDownloadUsage($employer);

            return response()->json([
                'success' => true,
                'message' => 'CV download usage updated successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
