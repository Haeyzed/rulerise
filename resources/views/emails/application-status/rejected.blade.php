<x-mail::message>
# Update on Your Application for {{ $jobDetails['title'] }}

<x-mail::icon src="{{ asset('images/leaf-icon.svg') }}" alt="Application Status Icon" />

<x-mail::divider />

Dear {{ $name }},

Thank you for taking the time to apply for the {{ $jobDetails['title'] }} position at {{ $jobDetails['company'] }}. We appreciate your interest and the effort you put into your application.

After careful consideration, we've decided to move forward with other candidates whose qualifications more closely align with the role at this time. This was a highly competitive process, and we were impressed by your background.

We encourage you to keep an eye on our [Job Portal]({{ $jobDetails['jobPortalUrl'] ?? '#' }}) for future opportunities that may be a better fit. We'd love to stay connected!

Thank you again, and we wish you all the best in your job search.

Best regards,  
**TalentBeyondBorders Team**
</x-mail::message>