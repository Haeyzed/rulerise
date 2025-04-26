<?php

namespace App\Services;

use App\Models\SubscriptionPlan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service class for subscription plan related operations
 */
class SubscriptionPlanService
{
    /**
     * Get all active subscription plans
     *
     * @return Collection
     */
    public function getActivePlans(): Collection
    {
        return SubscriptionPlan::where('is_active', true)->get();
    }

    /**
     * Get all subscription plans with pagination
     *
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getAllPlans(int $perPage = 10): LengthAwarePaginator
    {
        return SubscriptionPlan::orderBy('price')->paginate($perPage);
    }

    /**
     * Get a specific subscription plan
     *
     * @param int $id
     * @return SubscriptionPlan
     */
    public function getPlan(int $id): SubscriptionPlan
    {
        return SubscriptionPlan::findOrFail($id);
    }

    /**
     * Create a new subscription plan
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function createPlan(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    /**
     * Update an existing subscription plan
     *
     * @param int $id
     * @param array $data
     * @return SubscriptionPlan
     */
    public function updatePlan(int $id, array $data): SubscriptionPlan
    {
        $plan = $this->getPlan($id);
        $plan->update($data);
        return $plan;
    }

    /**
     * Toggle the active status of a subscription plan
     *
     * @param int $id
     * @param bool $isActive
     * @return SubscriptionPlan
     */
    public function togglePlanStatus(int $id, bool $isActive): SubscriptionPlan
    {
        $plan = $this->getPlan($id);
        $plan->is_active = $isActive;
        $plan->save();
        return $plan;
    }

    /**
     * Delete a subscription plan
     *
     * @param int $id
     * @return bool
     */
    public function deletePlan(int $id): bool
    {
        $plan = $this->getPlan($id);
        
        // Check if the plan has any active subscriptions
        if ($plan->subscriptions()->where('is_active', true)->exists()) {
            throw new \Exception('Cannot delete a plan with active subscriptions');
        }
        
        return $plan->delete();
    }

    /**
     * Compare two subscription plans
     *
     * @param SubscriptionPlan $planA
     * @param SubscriptionPlan $planB
     * @return array
     */
    public function comparePlans(SubscriptionPlan $planA, SubscriptionPlan $planB): array
    {
        return [
            'price_difference' => $planB->price - $planA->price,
            'duration_difference' => $planB->duration_days - $planA->duration_days,
            'job_posts_difference' => $planB->job_posts - $planA->job_posts,
            'featured_jobs_difference' => $planB->featured_jobs - $planA->featured_jobs,
            'cv_downloads_difference' => $planB->cv_downloads - $planA->cv_downloads,
        ];
    }
}
