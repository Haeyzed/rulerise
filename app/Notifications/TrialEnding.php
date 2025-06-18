<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Trial Ending Notification
 *
 * Sent when a trial period is about to end
 */
class TrialEnding extends Notification //implements ShouldQueue
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
        $daysRemaining = $this->subscription->getRemainingTrialDays();

        return (new MailMessage)
            ->subject('⏰ Your Trial is Ending Soon - Action Required')
            ->greeting("Hello {$employer->getCompanyDisplayName()}!")
            ->line("Your free trial is ending in {$daysRemaining} days.")
            ->line($this->getTrialDetailsMarkdown())
            ->line($this->getActionRequiredMarkdown())
            ->line($this->getWhatHappensNextMarkdown())
            ->action('Update Payment Method', url('/employer/billing'))
            ->line('Questions? Our support team is here to help!');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'trial_ending',
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'days_remaining' => $this->subscription->getRemainingTrialDays(),
            'trial_end_date' => $this->subscription->trial_end_date?->toISOString(),
            'message' => $this->getNotificationMessage(),
        ];
    }

    private function getTrialDetailsMarkdown(): string
    {
        $plan = $this->subscription->plan;
        $daysRemaining = $this->subscription->getRemainingTrialDays();
        $trialEndDate = $this->subscription->trial_end_date;

        $details = [];
        $details[] = "**Current Plan:** {$plan->name}";
        $details[] = "**Days Remaining:** {$daysRemaining}";

        if ($trialEndDate) {
            $details[] = "**Trial Ends:** {$trialEndDate->format('M j, Y')}";
        }

        $details[] = "**Next Billing Amount:** {$this->subscription->getFormattedAmount()}";

        return implode("\n", $details);
    }

    private function getActionRequiredMarkdown(): string
    {
        return "### Action Required\n\n" .
            "To continue enjoying uninterrupted access to your premium features:\n\n" .
            "1. **Update Payment Method:** Ensure your payment information is current\n" .
            "2. **Review Plan Details:** Confirm your subscription preferences\n" .
            "3. **No Action Needed:** If everything looks good, billing will start automatically\n\n" .
            "**Important:** No charges will occur during your trial period.";
    }

    private function getWhatHappensNextMarkdown(): string
    {
        $plan = $this->subscription->plan;
        $billingCycle = $plan->getBillingCycleLabel();

        return "### What Happens Next\n\n" .
            "When your trial ends:\n\n" .
            "✅ Your subscription will automatically continue\n" .
            "✅ You'll be charged {$this->subscription->getFormattedAmount()} {$billingCycle}\n" .
            "✅ All premium features remain available\n" .
            "✅ You can cancel anytime from your dashboard\n\n" .
            "**Need to Cancel?** You can cancel your subscription anytime before the trial ends with no charges.";
    }

    private function getNotificationMessage(): string
    {
        $daysRemaining = $this->subscription->getRemainingTrialDays();
        $planName = $this->subscription->plan->name;

        return "Your {$planName} trial ends in {$daysRemaining} days. Please ensure your payment method is up to date.";
    }
}
