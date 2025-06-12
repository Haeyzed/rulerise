<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionCreated extends Notification //implements ShouldQueue
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
        $isInTrial = $this->subscription->isInTrial();
        $provider = ucfirst($this->subscription->payment_provider);

        $message = (new MailMessage)
            ->subject("Your {$plan->name} Subscription Has Been Created")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Thank you for subscribing to our {$plan->name} plan.")
            ->line("Your subscription has been successfully created with {$provider}.");

        if ($isInTrial) {
            $trialEndDate = $this->subscription->trial_end_date->format('F j, Y');
            $message->line("Your free trial period will end on {$trialEndDate}.")
                   ->line("You won't be charged until your trial period ends.");
        }

        $message->line("Subscription details:")
               ->line("- Plan: {$plan->name}")
               ->line("- Price: {$plan->price} {$plan->currency} per {$plan->billing_cycle}")
               ->line("- Payment Provider: {$provider}")
               ->action('View Subscription Details', url('/dashboard/subscriptions'))
               ->line('Thank you for using our platform!');

        return $message;
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
