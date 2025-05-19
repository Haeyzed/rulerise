<?php

namespace App\Notifications;

use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobNotificationTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApplicationStatusChanged extends Notification// implements ShouldQueue
{
    use Queueable;

    protected JobApplication $application;
    protected Job $job;
    protected string $status;
    protected ?JobNotificationTemplate $template;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        JobApplication $application,
        Job $job,
        string $status,
        ?JobNotificationTemplate $template = null
    ) {
        $this->application = $application;
        $this->job = $job;
        $this->status = $status;
        $this->template = $template;
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

            return $mail->action('View Application', url('/candidate/applications/' . $this->application->id));
        }

        // Default notifications based on status if no template is available
        $mail = (new MailMessage)
            ->greeting('Hello ' . $notifiable->first_name . ',');

        switch ($this->status) {
            case 'shortlisted':
                $mail->subject('You\'ve Been Shortlisted: ' . $this->job->title)
                    ->line('Congratulations! Your application for the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . ' has been shortlisted.')
                    ->line('The hiring team was impressed with your qualifications and experience.')
                    ->line('You may be contacted soon for the next steps in the recruitment process.');
                break;

            case 'rejected':
                $mail->subject('Update on Your Application: ' . $this->job->title)
                    ->line('Thank you for your interest in the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . '.')
                    ->line('After careful consideration, we regret to inform you that your application has not been selected to move forward in the recruitment process.')
                    ->line('We appreciate your interest in joining our team and wish you success in your job search.');
                break;

            case 'offer_sent':
                $mail->subject('Job Offer: ' . $this->job->title)
                    ->line('Congratulations! We are pleased to offer you the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . '.')
                    ->line('Please check your email for details regarding the offer, including compensation, benefits, and start date.')
                    ->line('We look forward to welcoming you to our team!');
                break;

            case 'hired':
                $mail->subject('Welcome to ' . $this->job->employer->company_name . '!')
                    ->line('Congratulations on accepting our offer for the position of ' . $this->job->title . '.')
                    ->line('We are excited to have you join our team.')
                    ->line('You will receive additional information about your onboarding process shortly.');
                break;

            default:
                $mail->subject('Update on Your Application: ' . $this->job->title)
                    ->line('Your application for the position of ' . $this->job->title . ' at ' . $this->job->employer->company_name . ' has been updated.')
                    ->line('Current status: ' . ucfirst($this->status))
                    ->line('You can check your application details in your dashboard.');
        }

        return $mail->action('View Application', url('/candidate/applications/' . $this->application->id));
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
            'status' => $this->status,
            'message' => 'Your application for ' . $this->job->title . ' has been updated to ' . ucfirst($this->status),
            'type' => 'application_status_changed',
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
        $candidate = $this->application->candidate;
        $candidateUser = $candidate->user;
        $employer = $this->job->employer;
        $employerUser = $employer->user;

        $replacements = [
            '{JOB_TITLE}' => $this->job->title,
            '{COMPANY_NAME}' => $employer->company_name,
            '{EMPLOYER_NAME}' => $employerUser->first_name . ' ' . $employerUser->last_name,
            '{CANDIDATE_NAME}' => $candidateUser->first_name . ' ' . $candidateUser->last_name,
            '{APPLICATION_STATUS}' => ucfirst($this->status),
            '{APPLICATION_DATE}' => $this->application->created_at->format('F j, Y'),
            '{STATUS_DATE}' => now()->format('F j, Y'),
            '{EMPLOYER_NOTES}' => $this->application->employer_notes ?? 'No additional notes provided',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
