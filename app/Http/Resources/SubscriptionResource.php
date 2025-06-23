<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'subscription_plan_id' => $this->subscription_plan_id,
            'start_date' => $this->start_date,
            'end_date' => $this->end_date,
            'amount_paid' => $this->amount_paid,
            'currency' => $this->currency,
            'payment_method' => $this->payment_provider,
            'transaction_id' => $this->transaction_id,
            'payment_reference' => $this->payment_reference,
            'subscription_id' => $this->subscription_id,
            'receipt_path' => $this->receipt_path,
            'job_posts_left' => $this->job_posts_left,
            'featured_jobs_left' => $this->featured_jobs_left,
            'cv_downloads_left' => $this->cv_downloads_left,
            'is_active' => $this->is_active,
            'is_suspended' => $this->is_suspended,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            // Computed properties
            'is_expired' => $this->isExpired(),
            'days_remaining' => $this->daysRemaining(),
            'status_text' => $this->getStatusText(),

            // Related data
            'plan' => new PlanResource($this->whenLoaded('plan')),
        ];
    }
}
