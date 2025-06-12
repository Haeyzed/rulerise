<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessful extends Notification //implements ShouldQueue
{
    use Queueable;

    protected Payment $payment;

    /**
     * Create a new notification instance.
     */
    public function __construct(Payment $payment)
    {
        $this->payment = $payment;
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
        $plan = $this->payment->plan;
        $provider = ucfirst($this->payment->payment_provider);
        $paymentType = $this->payment->payment_type === 'one_time' ? 'One-time payment' : 'Subscription payment';
        $paymentDate = $this->payment->paid_at->format('F j, Y');

        return (new MailMessage)
            ->subject("Payment Successful for {$plan->name}")
            ->greeting("Hello {$notifiable->user->name}!")
            ->line("Your payment for the {$plan->name} plan has been successfully processed.")
            ->line("Payment details:")
            ->line("- Plan: {$plan->name}")
            ->line("- Amount: {$this->payment->amount} {$this->payment->currency}")
            ->line("- Payment Type: {$paymentType}")
            ->line("- Payment Provider: {$provider}")
            ->line("- Payment Date: {$paymentDate}")
            ->line("- Payment ID: {$this->payment->payment_id}")
            ->action('View Payment Details', url('/dashboard/payments'))
            ->line('Thank you for your payment!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'payment_id' => $this->payment->id,
            'plan_name' => $this->payment->plan->name,
            'payment_provider' => $this->payment->payment_provider,
            'amount' => $this->payment->amount,
            'currency' => $this->payment->currency,
        ];
    }
}
