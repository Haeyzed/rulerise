<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Services\EmployerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

/**
 * Controller for managing subscriptions
 */
class SubscriptionsController extends Controller implements HasMiddleware
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
            return response()->notFound('No active subscription found');
        }

        return response()->success($subscription, 'Subscription information');
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

            return response()->success('CV download usage updated successfully');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
}
