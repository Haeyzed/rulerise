<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    /**
     * Get employer's subscriptions
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $user = Auth::user();
        $employer = $user->employer;

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found',
            ], 404);
        }

        $subscriptions = $employer->subscriptions()
            ->with('plan')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptions' => $subscriptions
            ]
        ]);
    }

    /**
     * Get employer's active subscription
     *
     * @return JsonResponse
     */
    public function getActiveSubscription(): JsonResponse
    {
        $user = Auth::user();
        $employer = $user->employer;

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found',
            ], 404);
        }

        $activeSubscription = $employer->activeSubscription;

        if (!$activeSubscription) {
            return response()->json([
                'success' => false,
                'message' => 'No active subscription found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $activeSubscription->load('plan')
            ]
        ]);
    }

    /**
     * Get a specific subscription
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $employer = $user->employer;

        if (!$employer) {
            return response()->json([
                'success' => false,
                'message' => 'Employer profile not found',
            ], 404);
        }

        $subscription = $employer->subscriptions()
            ->with('plan')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription
            ]
        ]);
    }
}
