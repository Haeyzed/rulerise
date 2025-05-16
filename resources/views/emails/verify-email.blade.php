<x-mail::message>
# Verify Your Email

<x-mail::icon src="{{ asset('images/email-verification-icon.svg') }}" alt="Email Verification Icon" />

<x-mail::divider />

Welcome!

Please click the button below to verify your email address.

<x-mail::button :url="$url">
VERIFY EMAIL ADDRESS
</x-mail::button>

If you did not create an account, no further action is required.

Best regards,  
**TalentBeyondBorders Team**

<x-mail::subcopy>
If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:  
[{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>