<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Subscription Resumed Notification
 *
 * Sent when a suspended subscription is resumed
 */
class SubscriptionResumed extends Notification //implements ShouldQueue
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
            ->subject('🎉 Subscription Resumed - Welcome Back!')
            ->greeting("Hello {$employer->getCompanyDisplayName()}!")
            ->line('Great news! Your subscription has been successfully resumed.')
            ->line($this->getResumptionDetailsMarkdown())
            ->line($this->getAccessRestoredMarkdown())
            ->line($this->getNextStepsMarkdown())
            ->action('Access Your Dashboard', url('/employer/dashboard'))
            ->line('Thank you for resolving the issue. We\'re glad to have you back!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_resumed',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'resumed_at' => now()->toISOString(),
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getResumptionDetailsMarkdown(): string
    {
        $details = [];

        $details[] = "**Resumed Plan:** {$this->subscription->plan->name}";
        $details[] = "**Resumption Date:** " . now()->format('M j, Y');

        if ($this->subscription->next_billing_date) {
            $details[] = "**Next Billing:** {$this->subscription->next_billing_date->format('M j, Y')}";
        }

        return implode("\n", $details);
    }

    private function getAccessRestoredMarkdown(): string
    {
        $plan = $this->subscription->plan;
        $features = $plan->getFeaturesList();

        $message = "### Your Access Has Been Restored\n\n";
        $message .= "All premium features are now available:\n\n";

        foreach ($features as $feature) {
            $message .= "✅ {$feature}\n";
        }

        return $message;
    }

    private function getNextStepsMarkdown(): string
    {
        return "### What's Next\n\n" .
               "1. **Dashboard Access:** All features are immediately available\n" .
               "2. **Billing Cycle:** Your regular billing schedule has resumed\n" .
               "3. **Support:** Contact us if you experience any issues\n\n" .
               "**Questions?** Our support team is here to help at [support@example.com](mailto:support@example.com)";
    }

    private function getNotificationMessage(): string
    {
        return "Your {$this->subscription->plan->name} subscription has been resumed and all features are now available.";
    }
}
