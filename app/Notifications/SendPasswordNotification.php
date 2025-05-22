<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendPasswordNotification extends Notification
{
    use Queueable;

    protected string $password;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $password)
    {
        $this->password = $password;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail']; // You can add other channels here
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url') . '/admin/login';

        return (new MailMessage)
            ->subject('Your Account Password')
            ->markdown('emails.password-notification', [
                'password' => $this->password,
                'user' => $notifiable,
                'loginUrl' => $loginUrl,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
