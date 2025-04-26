<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CandidateApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected JobApplication $application;
    protected Job $job;

    /**
     * Create a new notification instance.
     */
    public function __construct(JobApplication $application, Job $job)
    {
        $this->application = $application;
        $this->job = $job;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Application Submitted: ' . $this->job->title)
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('Your application for the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . ' has been successfully submitted.')
            ->line('The employer will review your application and contact you if they wish to proceed with your candidacy.')
            ->line('You can track the status of your application in your dashboard.')
            ->action('View Your Applications', url('/candidate/applications'))
            ->line('Thank you for using our platform to find your next career opportunity!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'application_id' => $this->application->id,
            'job_id' => $this->job->id,
            'job_title' => $this->job->title,
            'employer_name' => $this->job->employer->company_name,
            'message' => 'Your application for ' . $this->job->title . ' has been submitted',
            'type' => 'application_submitted',
        ];
    }
}
