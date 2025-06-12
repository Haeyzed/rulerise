<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCancelled extends Notification //implements ShouldQueue
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
            ->subject("Your {$plan->name} Subscription Has Been Cancelled")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Your subscription to the {$plan->name} plan has been cancelled.")
            ->line("You will continue to have access to your subscription benefits until the end of your current billing period.")
            ->line("Subscription details:")
            ->line("- Plan: {$plan->name}")
            ->line("- Payment Provider: {$provider}")
            ->line("- Cancelled on: " . now()->format('F j, Y'))
            ->action('View Subscription Details', url('/dashboard/subscriptions'))
            ->line("We're sorry to see you go. If you change your mind, you can subscribe again at any time.")
            ->line("If you have any feedback about how we could improve our service, please let us know.");
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
