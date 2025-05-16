<x-mail::message>
# Offer Letter & Next Steps for Your Role at {{ $jobDetails['company'] }}

<x-mail::icon src="{{ asset('images/contract-icon.svg') }}" alt="Contract Icon" />

<x-mail::divider />

Dear {{ $name }},

Big news! {{ $jobDetails['company'] }} is thrilled to extend an offer for the position of {{ $jobDetails['title'] }}. Congratulations!

**Offer Details:**
- Position: {{ $jobDetails['title'] }}
- Start Date: {{ $jobDetails['startDate'] ?? '[Date]' }}
- Salary & Benefits: {{ $jobDetails['salaryDetails'] ?? '[Attached/Summarized]' }}
- Work Location: {{ $jobDetails['location'] ?? '[Remote/On-site in Canada]' }}

**Immigration & Relocation Support:**
Since you'll be relocating to Canada, here's what's next:
- Work Permit Processing: Our team will guide you through the necessary steps.
- Documentation Required: {{ $jobDetails['requiredDocuments'] ?? '[List of documents, e.g., passport, education certificates]' }}.
- Relocation Assistance: {{ $jobDetails['relocationAssistance'] ?? '[If applicable, mention support like flights, housing, etc.]' }}

**Action Required:**
- Sign and return the attached offer letter by {{ $jobDetails['offerDeadline'] ?? '[Deadline]' }}.
- Confirm your availability for an onboarding call on {{ $jobDetails['onboardingDate'] ?? '[Date]' }}.

We're excited to welcome you to the team! If you have any questions, reply to this email {{ $jobDetails['contactEmail'] ?? '[Company Email]' }} or contact {{ $jobDetails['hrContact'] ?? '[HR Contact Name]' }}.

Warm regards,  
**TalentBeyondBorders Team**
</x-mail::message>