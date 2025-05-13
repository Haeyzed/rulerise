<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionPlanController extends Controller
{
    /**
     * Get all active subscription plans
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $plans = SubscriptionPlan::query()->where('is_active', true)->get();

        return response()->json([
            'success' => true,
            'data' => [
                'plans' => $plans
            ]
        ]);
    }

    /**
     * Get a specific subscription plan
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $plan = SubscriptionPlan::query()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'plan' => $plan
            ]
        ]);
    }
}
