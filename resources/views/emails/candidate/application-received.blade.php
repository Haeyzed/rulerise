<x-mail::message>
# Application Submitted Successfully

Hello {{ $candidateName }},

Your application for the position of **{{ $job->title }}** at **{{ $employer->company_name }}** has been successfully submitted.

## Application Details
**Position:** {{ $job->title }}  
**Company:** {{ $employer->company_name }}  
**Location:** {{ $job->location }}  
**Application Date:** {{ $application->created_at->format('F j, Y') }}  
@if($resumeUsed)
**Resume:** Your uploaded resume was included with this application  
@else
**Resume:** Your profile information was used for this application  
@endif

## What's Next?
The employer will review your application and may contact you for further steps in the hiring process. You can track the status of your application in your dashboard.

<x-mail::button :url="$viewApplicationUrl">
Track Your Application
</x-mail::button>

<x-mail::button :url="$viewJobUrl">
View Job Details
</x-mail::button>

If you have any questions about your application or need to update any information, please log in to your account and visit the "Applied Jobs" section.

Best of luck with your application!

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
