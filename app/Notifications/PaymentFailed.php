<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment Failed Notification
 *
 * Sent when a payment fails to process
 */
class PaymentFailed extends Notification //implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Subscription $subscription,
        private array $failureDetails
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $employer = $this->subscription->employer;

        return (new MailMessage)
            ->subject('Payment Failed - Action Required')
            ->greeting("Hello {$employer->getCompanyDisplayName()},")
            ->line('We were unable to process your recent payment.')
            ->line($this->getFailureDetailsMarkdown())
            ->line($this->getImpactInformationMarkdown())
            ->line($this->getResolutionStepsMarkdown())
            ->action('Update Payment Method', url('/employer/billing'))
            ->line('Please resolve this issue promptly to avoid service interruption.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_failed',
            'subscription_id' => $this->subscription->id,
            'payment_id' => $this->failureDetails['payment_id'] ?? null,
            'amount' => $this->failureDetails['amount'] ?? $this->subscription->amount,
            'currency' => $this->failureDetails['currency'] ?? $this->subscription->currency,
            'failure_reason' => $this->failureDetails['failure_reason'] ?? 'Payment processing failed',
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getFailureDetailsMarkdown(): string
    {
        $amount = $this->failureDetails['amount'] ?? $this->subscription->amount;
        $currency = strtoupper($this->failureDetails['currency'] ?? $this->subscription->currency);
        $reason = $this->failureDetails['failure_reason'] ?? 'Payment processing failed';

        $details = [];
        $details[] = "**Failed Amount:** {$amount} {$currency}";
        $details[] = "**Plan:** {$this->subscription->plan->name}";
        $details[] = "**Failure Date:** " . now()->format('M j, Y');
        $details[] = "**Reason:** {$reason}";

        return implode("\n", $details);
    }

    private function getImpactInformationMarkdown(): string
    {
        return "### What This Means\n\n" .
               "⚠️ Your subscription may be at risk of suspension\n" .
               "⚠️ Access to premium features could be limited\n" .
               "⚠️ We'll retry the payment automatically\n" .
               "✅ Your account data remains safe and secure\n" .
               "✅ Existing services continue for now";
    }

    private function getResolutionStepsMarkdown(): string
    {
        return "### How to Resolve\n\n" .
               "1. **Check Payment Method:** Ensure your card details are correct and up to date\n" .
               "2. **Verify Funds:** Make sure you have sufficient funds available\n" .
               "3. **Contact Bank:** Check if your bank blocked the transaction\n" .
               "4. **Update Information:** Add a new payment method if needed\n" .
               "5. **Retry Payment:** We'll automatically retry, or you can manually retry\n\n" .
               "### Common Causes\n\n" .
               "- Expired credit card\n" .
               "- Insufficient funds\n" .
               "- Bank security restrictions\n" .
               "- Incorrect billing information\n\n" .
               "**Need Help?** Contact our support team at [support@example.com](mailto:support@example.com)";
    }

    private function getNotificationMessage(): string
    {
        $amount = $this->failureDetails['amount'] ?? $this->subscription->amount;
        $currency = strtoupper($this->failureDetails['currency'] ?? $this->subscription->currency);

        return "Payment of {$amount} {$currency} for your {$this->subscription->plan->name} subscription failed. Please update your payment method.";
    }
}
