<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
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
     * Subscription service instance
     *
     * @var SubscriptionService
     */
    protected SubscriptionService $subscriptionService;

    /**
     * Create a new controller instance.
     *
     * @param SubscriptionService $subscriptionService
     * @return void
     */
    public function __construct(SubscriptionService $subscriptionService)
    {
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

        $subscription = $this->subscriptionService->getActiveSubscription($employer);

        if (!$subscription) {
            return response()->notFound('No active subscription found');
        }

        return response()->success($subscription, 'Subscription information retrieved successfully');
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
            $result = $this->subscriptionService->decrementCvDownloadsLeft($employer);
            
            if (!$result) {
                return response()->badRequest('No CV downloads left or no active subscription');
            }

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
        
        try {
            $subscription = $employer->subscriptions()->findOrFail($id);
            
            $result = $this->subscriptionService->cancelSubscription($subscription);
            
            if (!$result) {
                return response()->badRequest('Failed to cancel subscription');
            }
            
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
        $subscriptions = $this->subscriptionService->getSubscriptionHistory($employer, $perPage);
        
        return response()->paginatedSuccess($subscriptions, 'Subscriptions retrieved successfully');
    }
}
