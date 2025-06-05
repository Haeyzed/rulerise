<x-mail::message>
# New Job Application Received

Hello {{ $employerName }},

You have received a new application for the position: **{{ $job->title }}**

## Candidate Information
**Name:** {{ $candidateUser->full_name }}
**Email:** {{ $candidateUser->email }}
**Phone:** {{ $candidateUser->phone ?? 'Not provided' }}
**Current Position:** {{ $candidate->current_position ?? 'Not provided' }}
**Current Company:** {{ $candidate->current_company ?? 'Not provided' }}
**Experience:** {{ $candidate->year_of_experience ?? 'Not specified' }} years

{{ $resumeText }}

@if($application->cover_letter)
## Cover Letter
{{ $application->cover_letter }}
@endif

<x-mail::button :url="$viewApplicationUrl">
View Application Details
</x-mail::button>

{{--<x-mail::button :url="$viewCandidateUrl">--}}
{{--View Candidate Profile--}}
{{--</x-mail::button>--}}

{{--<x-mail::button :url="$viewJobUrl">--}}
{{--View Job Posting--}}
{{--</x-mail::button>--}}

You can review this application and other applications for this position in your employer dashboard.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
