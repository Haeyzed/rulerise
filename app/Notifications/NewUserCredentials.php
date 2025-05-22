<?php

namespace App\Notifications;

use App\Models\Employer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewUserCredentials extends Notification// implements ShouldQueue
{
    use Queueable;

    /**
     * The password for the new user.
     *
     * @var string
     */
    protected string $password;

    /**
     * The employer that created the user.
     *
     * @var Employer
     */
    protected Employer $employer;

    /**
     * Create a new notification instance.
     *
     * @param string $password
     * @param Employer $employer
     * @return void
     */
    public function __construct(string $password, Employer $employer)
    {
        $this->password = $password;
        $this->employer = $employer;
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
     *
     * @param object $notifiable
     * @return MailMessage
     */
    public function toMail(object $notifiable): MailMessage
    {
        $loginUrl = config('app.frontend_url') . '/employer/login';

        return (new MailMessage)
            ->subject('Your Staff Account Credentials')
            ->markdown('emails.new-user-credentials', [
                'user' => $notifiable,
                'password' => $this->password,
                'employer' => $this->employer,
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
            'employer_id' => $this->employer->id,
            'employer_name' => $this->employer->company_name,
        ];
    }
}
