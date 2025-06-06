<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

class SubscriptionActivatedNotification extends Notification// implements ShouldQueue
{
    use Queueable;

    protected Subscription $subscription;

    /**
     * Create a new notification instance.
     *
     * @param Subscription $subscription
     */
    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $employer = $this->subscription->employer;
        $plan = $this->subscription->plan;
        $user = $employer->user;

        return (new MailMessage)
            ->subject('Subscription Activated: ' . $plan->name)
            ->markdown('emails.subscriptions.activated', [
                'subscription' => $this->subscription,
                'plan' => $plan,
                'employer' => $employer,
                'user' => $user,
                'isRecurring' => $plan->isRecurring(),
                'isOneTime' => $plan->isOneTime(),
                'nextBillingDate' => $this->subscription->next_billing_date ? Carbon::parse($this->subscription->next_billing_date)->format('d/m/Y') : null,
                'paymentMethod' => $this->subscription->billing_info['payment_method'] ?? null,
                'lastFour' => $this->subscription->billing_info['last_four'] ?? null,
                'url' => url('/employer/dashboard/subscriptions')
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray($notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'amount' => $this->subscription->amount_paid,
            'currency' => $this->subscription->currency,
            'payment_type' => $this->subscription->payment_type,
            'cv_downloads' => $this->subscription->plan->resume_views_limit,
            'is_recurring' => $this->subscription->plan->isRecurring(),
        ];
    }
}
