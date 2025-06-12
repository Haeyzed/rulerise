<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionSuspended extends Notification //implements ShouldQueue
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

        return (new MailMessage)
            ->subject("Your {$plan->name} Subscription Has Been Suspended")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Your subscription to the {$plan->name} plan has been suspended.")
            ->line("While suspended, you will not have access to the subscription benefits and you will not be charged.")
            ->line("Subscription details:")
            ->line("- Plan: {$plan->name}")
            ->line("- Payment Provider: {$provider}")
            ->line("- Suspended on: " . now()->format('F j, Y'))
            ->action('Resume Subscription', url('/dashboard/subscriptions'))
            ->line("You can resume your subscription at any time from your dashboard.")
            ->line("If you have any questions, please contact our support team.");
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
