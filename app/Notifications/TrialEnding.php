<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TrialEnding extends Notification //implements ShouldQueue
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
        $trialEndDate = $this->subscription->trial_end_date->format('F j, Y');
        $daysLeft = now()->diffInDays($this->subscription->trial_end_date);

        return (new MailMessage)
            ->subject("Your {$plan->name} Trial is Ending Soon")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Your free trial of the {$plan->name} plan will end in {$daysLeft} days on {$trialEndDate}.")
            ->line("After your trial ends, your payment method will be charged {$plan->price} {$plan->currency} for your first billing period.")
            ->line("Subscription details:")
            ->line("- Plan: {$plan->name}")
            ->line("- Price: {$plan->price} {$plan->currency} per {$plan->billing_cycle}")
            ->line("- Payment Provider: {$provider}")
            ->line("- Trial End Date: {$trialEndDate}")
            ->action('Manage Subscription', url('/dashboard/subscriptions'))
            ->line("If you don't want to continue with the subscription, please cancel before your trial ends to avoid being charged.")
            ->line("We hope you've been enjoying your trial!");
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
            'trial_end_date' => $this->subscription->trial_end_date,
        ];
    }
}
