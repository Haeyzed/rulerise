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

class EmployerApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    protected JobApplication $application;
    protected Candidate $candidate;
    protected Job $job;
    protected ?JobNotificationTemplate $template;

    /**
     * Create a new notification instance.
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
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $candidateUser = $this->candidate->user;
        $employer = $this->job->employer;
        
        // If we have a template, use it
        if ($this->template) {
            $subject = $this->replaceTemplatePlaceholders($this->template->subject);
            $content = $this->replaceTemplatePlaceholders($this->template->content);
            
            $mail = (new MailMessage)
                ->subject($subject)
                ->greeting('Hello ' . $notifiable->first_name . ',');
            
            // Split content by newlines and add each line
            foreach (explode("\n", $content) as $line) {
                if (!empty(trim($line))) {
                    $mail->line(trim($line));
                }
            }
            
            return $mail->action('View Applications', url('/employer/applications'));
        }
        
        // Default notification if no template is available
        return (new MailMessage)
            ->subject('New Application: ' . $this->job->title)
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('A new candidate has applied for the position of ' . $this->job->title . '.')
            ->line('Candidate: ' . $candidateUser->first_name . ' ' . $candidateUser->last_name)
            ->line('Email: ' . $candidateUser->email)
            ->line('You can review this application in your dashboard.')
            ->action('View Applications', url('/employer/applications'))
            ->line('This is an automated notification.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $candidateUser = $this->candidate->user;
        $candidateName = $candidateUser->first_name . ' ' . $candidateUser->last_name;
        
        return [
            'application_id' => $this->application->id,
            'job_id' => $this->job->id,
            'job_title' => $this->job->title,
            'candidate_id' => $this->candidate->id,
            'candidate_name' => $candidateName,
            'message' => 'New application from ' . $candidateName . ' for ' . $this->job->title,
            'type' => 'new_application',
        ];
    }
    
    /**
     * Replace placeholders in template with actual values.
     *
     * @param string $text
     * @return string
     */
    private function replaceTemplatePlaceholders(string $text): string
    {
        $candidateUser = $this->candidate->user;
        $employer = $this->job->employer;
        $employerUser = $employer->user;
        
        $replacements = [
            '{JOB_TITLE}' => $this->job->title,
            '{COMPANY_NAME}' => $employer->company_name,
            '{EMPLOYER_NAME}' => $employerUser->first_name . ' ' . $employerUser->last_name,
            '{CANDIDATE_NAME}' => $candidateUser->first_name . ' ' . $candidateUser->last_name,
            '{CANDIDATE_EMAIL}' => $candidateUser->email,
            '{CANDIDATE_PHONE}' => $candidateUser->phone ?? 'Not provided',
            '{APPLICATION_DATE}' => $this->application->created_at->format('F j, Y'),
        ];
        
        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
