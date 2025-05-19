<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CandidateApplicationReceived extends Notification// implements ShouldQueue
{
    use Queueable;

    /**
     * The job application instance.
     *
     * @var JobApplication
     */
    protected JobApplication $application;

    /**
     * The job instance.
     *
     * @var Job
     */
    protected Job $job;

    /**
     * Create a new notification instance.
     *
     * @param JobApplication $application
     * @param Job $job
     * @return void
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
        return ['mail'
//            , 'database'
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $employer = $this->job->employer;

        return (new MailMessage)
            ->subject('Your Application for ' . $this->job->title . ' has been submitted')
            ->markdown('emails.candidate.application-received', [
                'application' => $this->application,
                'job' => $this->job,
                'employer' => $employer,
                'candidateName' => $notifiable->first_name,
                'viewApplicationUrl' => config('app.frontend_url') . '/candidate/applications/' . $this->application->id,
                'viewJobUrl' => config('app.frontend_url') . '/jobs/' . $this->job->id,
                'resumeUsed' => $this->application->resume_id ? true : false,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $employer = $this->job->employer;

        return [
            'application_id' => $this->application->id,
            'job_id' => $this->job->id,
            'job_title' => $this->job->title,
            'employer_id' => $employer->id,
            'employer_name' => $employer->company_name,
            'message' => 'Your application for ' . $this->job->title . ' at ' . $employer->company_name . ' has been submitted',
            'type' => 'application_submitted',
        ];
    }
}
