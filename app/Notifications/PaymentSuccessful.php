<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Payment Successful Notification
 *
 * Sent when a payment is successfully processed
 */
class PaymentSuccessful extends Notification //implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private Subscription $subscription,
        private array $paymentDetails
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $employer = $this->subscription->employer;
        $amount = $this->paymentDetails['amount'] ?? $this->subscription->amount;
        $currency = $this->paymentDetails['currency'] ?? $this->subscription->currency;

        return (new MailMessage)
            ->subject('Payment Successful - Thank You!')
            ->greeting("Hello {$employer->getCompanyDisplayName()}!")
            ->line('Your payment has been successfully processed.')
            ->line($this->getPaymentDetailsMarkdown())
            ->line($this->getSubscriptionStatusMarkdown())
            ->line($this->getReceiptInformationMarkdown())
            ->action('View Dashboard', url('/employer/dashboard'))
            ->line('Thank you for your continued trust in our platform!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payment_successful',
            'subscription_id' => $this->subscription->id,
            'payment_id' => $this->paymentDetails['payment_id'] ?? null,
            'amount' => $this->paymentDetails['amount'] ?? $this->subscription->amount,
            'currency' => $this->paymentDetails['currency'] ?? $this->subscription->currency,
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getPaymentDetailsMarkdown(): string
    {
        $amount = $this->paymentDetails['amount'] ?? $this->subscription->amount;
        $currency = strtoupper($this->paymentDetails['currency'] ?? $this->subscription->currency);
        $paymentId = $this->paymentDetails['payment_id'] ?? 'N/A';

        $details = [];
        $details[] = "**Amount:** {$amount} {$currency}";
        $details[] = "**Plan:** {$this->subscription->plan->name}";
        $details[] = "**Payment Date:** " . now()->format('M j, Y');
        $details[] = "**Payment ID:** {$paymentId}";

        return implode("\n", $details);
    }

    private function getSubscriptionStatusMarkdown(): string
    {
        if ($this->subscription->isInTrial()) {
            $trialDays = $this->subscription->getRemainingTrialDays();
            return "### Subscription Status\n\n" .
                   "Your subscription is active with **{$trialDays} days** remaining in your trial period.\n" .
                   "This payment will be applied when your trial ends.";
        }

        $nextBilling = $this->subscription->next_billing_date;
        if ($nextBilling) {
            return "### Subscription Status\n\n" .
                   "Your subscription is active and in good standing.\n" .
                   "**Next billing date:** {$nextBilling->format('M j, Y')}";
        }

        return "### Subscription Status\n\n" .
               "Your subscription is active and all features are available.";
    }

    private function getReceiptInformationMarkdown(): string
    {
        return "### Receipt Information\n\n" .
               "This email serves as your payment receipt. Please keep it for your records.\n\n" .
               "**Need a detailed invoice?** You can download invoices from your dashboard.\n" .
               "**Questions about billing?** Contact our support team at [billing@example.com](mailto:billing@example.com)";
    }

    private function getNotificationMessage(): string
    {
        $amount = $this->paymentDetails['amount'] ?? $this->subscription->amount;
        $currency = strtoupper($this->paymentDetails['currency'] ?? $this->subscription->currency);

        return "Payment of {$amount} {$currency} for your {$this->subscription->plan->name} subscription has been processed successfully.";
    }
}
