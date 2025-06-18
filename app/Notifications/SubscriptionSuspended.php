<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Subscription Suspended Notification
 *
 * Sent when a subscription is suspended
 */
class SubscriptionSuspended extends Notification //implements ShouldQueue
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
            ->subject('Subscription Suspended - Action Required')
            ->greeting("Hello {$employer->getCompanyDisplayName()},")
            ->line('Your subscription has been suspended and requires your attention.')
            ->line($this->getSuspensionDetailsMarkdown())
            ->line($this->getImpactInformationMarkdown())
            ->line($this->getResolutionStepsMarkdown())
            ->action('Resolve Suspension', url('/employer/dashboard'))
            ->line('Please contact our support team if you need assistance resolving this issue.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_suspended',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'suspended_at' => now()->toISOString(),
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getSuspensionDetailsMarkdown(): string
    {
        $details = [];

        $details[] = "**Suspended Plan:** {$this->subscription->plan->name}";
        $details[] = "**Suspension Date:** " . now()->format('M j, Y');
        $details[] = "**Reason:** Payment issue or account review";

        return implode("\n", $details);
    }

    private function getImpactInformationMarkdown(): string
    {
        return "### What This Means\n\n" .
               "While your subscription is suspended:\n\n" .
               "❌ Access to premium features is temporarily disabled\n" .
               "❌ New job postings may be limited\n" .
               "❌ Advanced analytics are unavailable\n" .
               "✅ Your account data remains safe and secure\n" .
               "✅ Existing job posts remain active";
    }

    private function getResolutionStepsMarkdown(): string
    {
        return "### How to Resolve\n\n" .
               "1. **Check Payment Method:** Ensure your payment information is up to date\n" .
               "2. **Review Account Status:** Check for any outstanding issues in your dashboard\n" .
               "3. **Contact Support:** Reach out if you need assistance\n" .
               "4. **Reactivate:** Once resolved, your subscription will be automatically reactivated\n\n" .
               "**Need Help?** Contact our support team at [support@example.com](mailto:support@example.com)";
    }

    private function getNotificationMessage(): string
    {
        return "Your {$this->subscription->plan->name} subscription has been suspended. Please check your account to resolve any issues.";
    }
}
