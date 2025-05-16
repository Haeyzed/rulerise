<x-mail::message>
# Congratulations! You've Been Shortlisted for an Interview at {{ $jobDetails['company'] }}

<x-mail::icon src="{{ asset('images/congratulations-icon.svg') }}" alt="Congratulations Icon" />

<x-mail::divider />

Dear {{ $name }},

We're excited to inform you that your application for {{ $jobDetails['title'] }} at {{ $jobDetails['company'] }} has been shortlisted! The hiring team was impressed by your skills and experience, and they'd like to invite you for an interview.

**Interview Details:**
- Date & Time: {{ $jobDetails['interviewDate'] ?? '[Date & Time]' }}
- Format: {{ $jobDetails['interviewFormat'] ?? '[Virtual/In-Person]' }}
- Meeting Link: {{ $jobDetails['meetingLink'] ?? '[Zoom/Teams Link] (if applicable)' }}
- Documents to Prepare: {{ $jobDetails['requiredDocuments'] ?? '[Resume, Portfolio, ID, etc.]' }}

**Next Steps:**
- Please confirm your availability by {{ $jobDetails['confirmationDeadline'] ?? '[Deadline, if applicable]' }}.
- If the scheduled time doesn't work, reply to this email with alternative slots.
- Prepare any additional materials as requested.

We wish you the best of luck! If you have any questions, feel free to reach out.

Best regards,
**TalentBeyondBorders Team**
</x-mail::message>
