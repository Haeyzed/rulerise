<?php

namespace App\Notifications;

use App\Models\Candidate;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobNotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployerApplicationReceived extends Notification// implements ShouldQueue
{
    use Queueable;

    /**
     * The job application instance.
     *
     * @var JobApplication
     */
    protected JobApplication $application;

    /**
     * The candidate instance.
     *
     * @var Candidate
     */
    protected Candidate $candidate;

    /**
     * The job instance.
     *
     * @var Job
     */
    protected Job $job;

    /**
     * The notification template instance.
     *
     * @var JobNotificationTemplate|null
     */
    protected ?JobNotificationTemplate $template;

    /**
     * Create a new notification instance.
     *
     * @param JobApplication $application
     * @param Candidate $candidate
     * @param Job $job
     * @param JobNotificationTemplate|null $template
     * @return void
     */
    public function __construct(
        JobApplication $application,
        Candidate $candidate,
        Job $job,
        ?JobNotificationTemplate $template = null
    ) {
        $this->application = $application;
        $this->candidate = $candidate;
        $this->job = $job;
        $this->template = $template;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail',
            'database'
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $candidateUser = $this->candidate->user;
        $resumeText = $this->application->resume_id ? 'The candidate has attached their resume.' : 'The candidate applied using their profile.';

        $mailMessage = (new MailMessage)
            ->subject('New Job Application Received: ' . $this->job->title)
            ->markdown('emails.employer.application-received', [
                'application' => $this->application,
                'candidate' => $this->candidate,
                'candidateUser' => $candidateUser,
                'job' => $this->job,
                'resumeText' => $resumeText,
//                'employerName' => $notifiable->first_name,
                'employerName' => $notifiable->first_name,
//                'viewApplicationUrl' => config('app.frontend_url') . '/employer/jobs/job-details?id=' . $this->job->id . '/applications/' . $this->application->id,
                'viewApplicationUrl' => config('app.frontend_url') . '/employer/jobs/job-details?id=' . $this->job->id,
                'viewCandidateUrl' => config('app.frontend_url') . '/employer/candidates/' . $this->candidate->id,
                'viewJobUrl' => config('app.frontend_url') . '/employer/jobs/' . $this->job->id,
            ]);

        // If there's a resume attached, add it as an attachment
        if ($this->application->resume_id && $this->application->resume && $this->application->resume->file_path) {
            $resumePath = storage_path('app/' . $this->application->resume->file_path);
            if (file_exists($resumePath)) {
                $mailMessage->attach($resumePath, [
                    'as' => $candidateUser->full_name . ' - Resume.pdf',
                    'mime' => 'application/pdf',
                ]);
            }
        }

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $candidateUser = $this->candidate->user;

        return [
            'application_id' => $this->application->id,
            'job_id' => $this->job->id,
            'job_title' => $this->job->title,
            'candidate_id' => $this->candidate->id,
            'candidate_name' => $candidateUser->full_name,
            'message' => 'New application received from ' . $candidateUser->full_name . ' for ' . $this->job->title,
            'type' => 'new_application',
        ];
    }
}
