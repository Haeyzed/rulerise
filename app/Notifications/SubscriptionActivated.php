<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionActivated extends Notification //implements ShouldQueue
{
    use Queueable;

    protected Subscription $subscription;

    /**
     * Create a new notification instance.
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
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->subscription->plan;
        $provider = ucfirst($this->subscription->payment_provider);
//        $nextBillingDate = $this->subscription->next_billing_date->format('F j, Y');
        $nextBillingDate = $this->subscription->next_billing_date->format('d M Y, H:i a');

        return (new MailMessage)
            ->subject("Your {$plan->name} Subscription is Now Active")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Your subscription to the {$plan->name} plan is now active.")
            ->line("You now have access to all the features included in your plan.")
            ->line("Subscription details:")
            ->line("- Plan: {$plan->name}")
            ->line("- Price: {$plan->price} {$plan->currency} per {$plan->billing_cycle}")
            ->line("- Payment Provider: {$provider}")
            ->line("- Next Billing Date: {$nextBillingDate}")
            ->action('View Subscription Details', url('/dashboard/subscriptions'))
            ->line('Thank you for using our platform!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subscription_id' => $this->subscription->id,
            'plan_name' => $this->subscription->plan->name,
            'payment_provider' => $this->subscription->payment_provider,
        ];
    }
}
