<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Services\EmployerService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
     * Subscription service instance
     *
     * @var SubscriptionService
     */
    protected SubscriptionService $subscriptionService;

    /**
     * Create a new controller instance.
     *
     * @param EmployerService $employerService
     * @param SubscriptionService $subscriptionService
     * @return void
     */
    public function __construct(EmployerService $employerService, SubscriptionService $subscriptionService)
    {
        $this->employerService = $employerService;
        $this->subscriptionService = $subscriptionService;
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
    
    /**
     * Cancel subscription
     *
     * @param int $id
     * @return JsonResponse
     */
    public function cancelSubscription(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $subscription = $employer->subscriptions()->findOrFail($id);
        
        try {
            $this->subscriptionService->cancelSubscription($subscription);
            
            return response()->success('Subscription cancelled successfully');
        } catch (\Exception $e) {
            return response()->badRequest($e->getMessage());
        }
    }
    
    /**
     * List all subscriptions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function listSubscriptions(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        
        $perPage = $request->input('per_page', 10);
        $subscriptions = $employer->subscriptions()
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->paginatedSuccess($subscriptions, 'Subscriptions retrieved successfully');
    }
}
