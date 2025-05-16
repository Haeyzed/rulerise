<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusNotification extends Notification
{
    use Queueable;

    /**
     * The status of the application.
     *
     * @var string
     */
    protected $status;

    /**
     * The job details.
     *
     * @var array
     */
    protected $jobDetails;

    /**
     * Create a new notification instance.
     *
     * @param string $status
     * @param array $jobDetails
     * @return void
     */
    public function __construct($status, array $jobDetails)
    {
        $this->status = $status;
        $this->jobDetails = $jobDetails;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $view = 'emails.application-status.' . strtolower($this->status);
        $subject = $this->getSubjectForStatus();

        return (new MailMessage)
            ->subject($subject)
            ->markdown($view, [
                'name' => $notifiable->name ?? 'Candidate',
                'jobDetails' => $this->jobDetails,
            ]);
    }

    /**
     * Get the subject line for the notification based on status.
     *
     * @return string
     */
    protected function getSubjectForStatus()
    {
        switch ($this->status) {
            case 'shortlisted':
                return "You're Shortlisted! - Interview Invitation";
            case 'rejected':
                return "Update on Your Application for {$this->jobDetails['title']}";
            case 'hired':
                return "Offer Letter & Next Steps for Your Role at {$this->jobDetails['company']}";
            default:
                return "Update on Your Job Application";
        }
    }
}