<?php

namespace App\Services;

use App\Models\Employer;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\Payment\PayPalService;
use App\Services\Payment\StripeService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    protected PayPalService $paypalService;
    protected StripeService $stripeService;

    public function __construct(PayPalService $paypalService, StripeService $stripeService)
    {
        $this->paypalService = $paypalService;
        $this->stripeService = $stripeService;
    }

    public function subscribe(Employer $employer, SubscriptionPlan $plan, string $paymentMethod, array $paymentData = []): array
    {
        try {
            DB::beginTransaction();

            $isTrialEligible = $employer->isEligibleForTrial() && $plan->hasTrial();

            $this->cancelActiveSubscriptions($employer);

            $subscription = $this->createSubscription($employer, $plan, $paymentMethod, $isTrialEligible);

            if ($isTrialEligible) {
                $result = $this->startTrialPeriod($employer, $subscription, $plan);
            } else {
                $result = $this->processPayment($subscription, $paymentMethod, $paymentData);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $subscription->fresh(['plan']),
                'is_trial' => $isTrialEligible,
                'payment_result' => $result
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Subscription failed', [
                'employer_id' => $employer->id,
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function createSubscription(Employer $employer, SubscriptionPlan $plan, string $paymentMethod, bool $isTrial = false): Subscription
    {
        $startDate = now();
        $endDate = null;

        if ($plan->isRecurring()) {
            $endDate = $this->calculateEndDate($startDate, $plan);
        }

        return Subscription::create([
            'employer_id' => $employer->id,
            'subscription_plan_id' => $plan->id,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'amount_paid' => $isTrial ? 0 : $plan->price,
            'currency' => $plan->currency,
            'payment_method' => $paymentMethod,
            'job_posts_left' => $plan->job_posts_limit,
            'featured_jobs_left' => $plan->featured_jobs_limit,
            'cv_downloads_left' => $plan->resume_views_limit,
            'is_active' => true,
            'is_suspended' => false,
            'used_trial' => $isTrial,
            'payment_type' => $plan->payment_type,
            'next_billing_date' => $plan->isRecurring() ? $endDate : null,
        ]);
    }

    protected function startTrialPeriod(Employer $employer, Subscription $subscription, SubscriptionPlan $plan): array
    {
        $trialEndDate = now()->addDays($plan->getTrialPeriodDays());

        $subscription->update([
            'end_date' => $trialEndDate,
            'external_status' => 'trialing',
            'next_billing_date' => $trialEndDate,
        ]);

        $employer->markTrialAsUsed();

        return [
            'status' => 'trial_started',
            'trial_end_date' => $trialEndDate,
            'message' => "Trial period started. You have {$plan->getTrialPeriodDays()} days to try the service."
        ];
    }

    protected function processPayment(Subscription $subscription, string $paymentMethod, array $paymentData): array
    {
        switch (strtolower($paymentMethod)) {
            case 'stripe':
                return $this->stripeService->processPayment($subscription, $paymentData);
            case 'paypal':
                return $this->paypalService->processPayment($subscription, $paymentData);
            default:
                throw new Exception("Unsupported payment method: {$paymentMethod}");
        }
    }

    protected function cancelActiveSubscriptions(Employer $employer): void
    {
        $activeSubscriptions = $employer->subscriptions()
            ->where('is_active', true)
            ->get();

        foreach ($activeSubscriptions as $subscription) {
            $this->cancelSubscription($subscription, 'replaced_by_new_subscription');
        }
    }

    public function cancelSubscription(Subscription $subscription, string $reason = 'user_requested'): array
    {
        try {
            DB::beginTransaction();

            if ($subscription->isRecurring() && $subscription->subscription_id) {
                $this->cancelWithPaymentGateway($subscription);
            }

            $subscription->update([
                'is_active' => false,
                'external_status' => 'cancelled',
                'status_update_time' => now(),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Subscription cancelled successfully'
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Subscription cancellation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function suspendSubscription(Subscription $subscription): array
    {
        try {
            $subscription->update([
                'is_suspended' => true,
                'external_status' => 'suspended',
                'status_update_time' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Subscription suspended successfully'
            ];

        } catch (Exception $e) {
            Log::error('Subscription suspension failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function reactivateSubscription(Subscription $subscription): array
    {
        try {
            $subscription->update([
                'is_suspended' => false,
                'external_status' => 'active',
                'status_update_time' => now(),
            ]);

            return [
                'success' => true,
                'message' => 'Subscription reactivated successfully'
            ];

        } catch (Exception $e) {
            Log::error('Subscription reactivation failed', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public function upgradeSubscription(Employer $employer, SubscriptionPlan $newPlan, string $paymentMethod, array $paymentData = []): array
    {
        $currentSubscription = $employer->activeSubscription;

        if (!$currentSubscription) {
            return $this->subscribe($employer, $newPlan, $paymentMethod, $paymentData);
        }

        try {
            DB::beginTransaction();

            $proratedAmount = $this->calculateProratedAmount($currentSubscription, $newPlan);

            $this->cancelSubscription($currentSubscription, 'upgraded');

            $newSubscription = $this->createSubscription($employer, $newPlan, $paymentMethod);

            if ($proratedAmount > 0) {
                $paymentData['amount'] = $proratedAmount;
                $result = $this->processPayment($newSubscription, $paymentMethod, $paymentData);

                $newSubscription->update([
                    'amount_paid' => $proratedAmount
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'subscription' => $newSubscription->fresh(['plan']),
                'prorated_amount' => $proratedAmount,
                'message' => 'Subscription upgraded successfully'
            ];

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Subscription upgrade failed', [
                'employer_id' => $employer->id,
                'new_plan_id' => $newPlan->id,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    protected function calculateProratedAmount(Subscription $currentSubscription, SubscriptionPlan $newPlan): float
    {
        if ($currentSubscription->isOneTime() || $newPlan->isOneTime()) {
            return $newPlan->price;
        }

        $remainingDays = $currentSubscription->daysRemaining();
        $totalDays = $currentSubscription->plan->duration_days ?? 30;

        $unusedAmount = ($remainingDays / $totalDays) * $currentSubscription->plan->price;
        $proratedAmount = $newPlan->price - $unusedAmount;

        return max(0, $proratedAmount);
    }

    protected function cancelWithPaymentGateway(Subscription $subscription): void
    {
        switch (strtolower($subscription->payment_method)) {
            case 'stripe':
                $this->stripeService->cancelSubscription($subscription);
                break;
            case 'paypal':
                $this->paypalService->cancelSubscription($subscription);
                break;
        }
    }

    protected function calculateEndDate(Carbon $startDate, SubscriptionPlan $plan): Carbon
    {
        $endDate = $startDate->copy();

        switch ($plan->interval_unit) {
            case SubscriptionPlan::INTERVAL_UNIT_DAY:
                return $endDate->addDays($plan->interval_count);
            case SubscriptionPlan::INTERVAL_UNIT_WEEK:
                return $endDate->addWeeks($plan->interval_count);
            case SubscriptionPlan::INTERVAL_UNIT_MONTH:
                return $endDate->addMonths($plan->interval_count);
            case SubscriptionPlan::INTERVAL_UNIT_YEAR:
                return $endDate->addYears($plan->interval_count);
            default:
                return $endDate->addDays($plan->duration_days ?? 30);
        }
    }

    public function verifyPayPalSubscription(array $data): array
    {
        return $this->paypalService->verifySubscription($data);
    }

    public function verifyStripeSubscription(array $data): array
    {
        return $this->stripeService->verifySubscription($data);
    }

    public function handleWebhook(string $gateway, array $data): array
    {
        switch (strtolower($gateway)) {
            case 'stripe':
                return $this->stripeService->handleWebhook($data);
            case 'paypal':
                return $this->paypalService->handleWebhook($data);
            default:
                throw new Exception("Unsupported gateway: {$gateway}");
        }
    }
}
