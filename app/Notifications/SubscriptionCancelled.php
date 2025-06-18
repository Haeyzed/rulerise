<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Subscription Cancelled Notification
 *
 * Sent when a subscription is cancelled
 */
class SubscriptionCancelled extends Notification //implements ShouldQueue
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
            ->subject('Subscription Cancelled - We\'re Sorry to See You Go')
            ->greeting("Hello {$employer->getCompanyDisplayName()},")
            ->line('We\'ve processed your subscription cancellation request.')
            ->line($this->getCancellationDetailsMarkdown())
            ->line($this->getAccessInformationMarkdown())
            ->line($this->getFeedbackRequestMarkdown())
            ->action('Reactivate Subscription', url('/employer/plans'))
            ->line('Thank you for being part of our community. We hope to serve you again in the future!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'subscription_cancelled',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'cancelled_at' => $this->subscription->canceled_at?->toISOString(),
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getCancellationDetailsMarkdown(): string
    {
        $details = [];

        $details[] = "**Cancelled Plan:** {$this->subscription->plan->name}";
        $details[] = "**Cancellation Date:** {$this->subscription->canceled_at->format('M j, Y')}";

        if ($this->subscription->end_date && $this->subscription->end_date->isFuture()) {
            $details[] = "**Access Until:** {$this->subscription->end_date->format('M j, Y')}";
        }

        return implode("\n", $details);
    }

    private function getAccessInformationMarkdown(): string
    {
        if ($this->subscription->end_date && $this->subscription->end_date->isFuture()) {
            $daysRemaining = now()->diffInDays($this->subscription->end_date);

            return "### Important Information\n\n" .
                   "Your subscription has been cancelled, but you still have **{$daysRemaining} days** " .
                   "of access remaining until {$this->subscription->end_date->format('M j, Y')}.\n\n" .
                   "You can continue to use all features during this period.";
        }

        return "### Access Status\n\n" .
               "Your subscription access has ended immediately. " .
               "You can reactivate your subscription at any time to regain access to premium features.";
    }

    private function getFeedbackRequestMarkdown(): string
    {
        return "### Help Us Improve\n\n" .
               "We'd love to hear your feedback about your experience. " .
               "Your insights help us improve our service for everyone.\n\n" .
               "[Share Your Feedback](" . url('/feedback') . ")";
    }

    private function getNotificationMessage(): string
    {
        $planName = $this->subscription->plan->name;

        if ($this->subscription->end_date && $this->subscription->end_date->isFuture()) {
            $daysRemaining = now()->diffInDays($this->subscription->end_date);
            return "Your {$planName} subscription has been cancelled. You have {$daysRemaining} days of access remaining.";
        }

        return "Your {$planName} subscription has been cancelled and access has ended.";
    }
}
