<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Subscription Created Notification
 *
 * Sent when a new subscription is created (before activation)
 */
class SubscriptionCreated extends Notification //implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Subscription $subscription
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $employer = $this->subscription->employer;

        return (new MailMessage)
            ->subject('Subscription Created - Complete Your Setup')
            ->greeting("Hello {$employer->getCompanyDisplayName()}!")
            ->line('Thank you for choosing our platform! Your subscription has been created.')
            ->line($this->getSubscriptionDetailsMarkdown())
            ->line($this->getNextStepsMarkdown())
            ->action('Complete Setup', $this->getCompletionUrl())
            ->line('If you have any questions, our support team is here to help!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_created',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'status' => $this->subscription->status,
            'requires_action' => $this->subscription->isPending(),
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getSubscriptionDetailsMarkdown(): string
    {
        $plan = $this->subscription->plan;
        $details = [];

        $details[] = "**Plan:** {$plan->name}";
        $details[] = "**Amount:** {$this->subscription->getFormattedAmount()}";
        $details[] = "**Billing Cycle:** {$plan->getBillingCycleLabel()}";
        $details[] = "**Status:** " . ucfirst($this->subscription->status);

        if ($this->subscription->isInTrial()) {
            $trialDays = $this->subscription->getRemainingTrialDays();
            $details[] = "**Trial Period:** {$trialDays} days";
        }

        return implode("\n", $details);
    }

    private function getNextStepsMarkdown(): string
    {
        if ($this->subscription->isPending()) {
            return "### Next Steps\n\n" .
                   "1. **Complete Payment:** Click the button below to finalize your subscription\n" .
                   "2. **Account Activation:** Your account will be activated immediately after payment\n" .
                   "3. **Start Using Features:** Access all premium features right away\n\n" .
                   "**Important:** Your subscription will remain pending until payment is completed.";
        }

        if ($this->subscription->isInTrial()) {
            return "### Your Trial Has Started\n\n" .
                   "1. **Full Access:** Enjoy all premium features during your trial\n" .
                   "2. **No Charges:** You won't be charged until your trial ends\n" .
                   "3. **Automatic Billing:** Billing will start automatically after the trial period\n\n" .
                   "**Trial Duration:** {$this->subscription->getRemainingTrialDays()} days remaining";
        }

        return "### Welcome to Premium\n\n" .
               "Your subscription is being processed and will be activated shortly. " .
               "You'll receive another notification once everything is ready.";
    }

    private function getCompletionUrl(): string
    {
        // Return the appropriate URL based on subscription status
        if ($this->subscription->isPending()) {
            return url('/employer/dashboard?action=complete_payment');
        }

        return url('/employer/dashboard');
    }

    private function getNotificationMessage(): string
    {
        $planName = $this->subscription->plan->name;

        if ($this->subscription->isPending()) {
            return "Your {$planName} subscription has been created. Please complete the payment to activate your account.";
        }

        if ($this->subscription->isInTrial()) {
            $trialDays = $this->subscription->getRemainingTrialDays();
            return "Your {$planName} subscription has been created with {$trialDays} days of free trial.";
        }

        return "Your {$planName} subscription has been created and is being processed.";
    }
}
