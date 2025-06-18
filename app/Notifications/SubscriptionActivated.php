<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Subscription Activated Notification
 *
 * Sent when a subscription is successfully activated
 */
class SubscriptionActivated extends Notification //implements ShouldQueue
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
            ->subject('🎉 Your Subscription is Now Active!')
            ->greeting("Hello {$employer->getCompanyDisplayName()}!")
            ->line('Great news! Your subscription has been successfully activated.')
            ->line($this->getSubscriptionDetailsMarkdown())
            ->line($this->getWelcomeMessageMarkdown())
            ->action('Access Your Dashboard', url('/employer/dashboard'))
            ->line('Thank you for choosing our platform to grow your business!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_activated',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'amount' => $this->subscription->getFormattedAmount(),
            'is_trial' => $this->subscription->isInTrial(),
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

        if ($this->subscription->isInTrial()) {
            $trialDays = $this->subscription->getRemainingTrialDays();
            $details[] = "**Trial Period:** {$trialDays} days remaining";
        }

        if ($this->subscription->next_billing_date) {
            $details[] = "**Next Billing:** {$this->subscription->next_billing_date->format('M j, Y')}";
        }

        return implode("\n", $details);
    }

    private function getWelcomeMessageMarkdown(): string
    {
        $plan = $this->subscription->plan;
        $features = $plan->getFeaturesList();

        $message = "### What's included in your {$plan->name} plan:\n\n";

        foreach ($features as $feature) {
            $message .= "✅ {$feature}\n";
        }

        if ($this->subscription->isInTrial()) {
            $message .= "\n**Trial Information:**\n";
            $message .= "You're currently in your free trial period. ";
            $message .= "Enjoy full access to all features with no charges until your trial ends.";
        }

        return $message;
    }

    private function getNotificationMessage(): string
    {
        $planName = $this->subscription->plan->name;

        if ($this->subscription->isInTrial()) {
            $trialDays = $this->subscription->getRemainingTrialDays();
            return "Your {$planName} subscription is now active with {$trialDays} days of free trial remaining.";
        }

        return "Your {$planName} subscription is now active and ready to use.";
    }
}
