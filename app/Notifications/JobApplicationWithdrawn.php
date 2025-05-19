<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class JobApplicationWithdrawn extends Notification// implements ShouldQueue
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
            ->subject('Application Withdrawn: ' . $this->job->title)
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('You have successfully withdrawn your application for the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . '.')
            ->line('We understand that circumstances change, and we appreciate you keeping your application status up to date.')
            ->line('You are welcome to apply for other positions or reapply for this position in the future if your circumstances change.')
            ->action('View Other Jobs', url('/jobs'))
            ->line('Thank you for your interest in ' . $this->job->employer->company_name . '.');
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
            'message' => 'You have withdrawn your application for ' . $this->job->title,
            'type' => 'application_withdrawn',
        ];
    }
}
