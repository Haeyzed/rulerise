<x-mail::message>
# Reset Your Password

<x-mail::icon src="{{ asset('images/key-icon.svg') }}" alt="Reset Password Icon" />

<x-mail::divider />

Hello {{ $name }},

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$url">
RESET PASSWORD
</x-mail::button>

This password reset link will expire in {{ $count }} minutes.

If you did not request a password reset, no further action is required.

Best regards,  
**TalentBeyondBorders Team**

<x-mail::subcopy>
If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:  
[{{ $url }}]({{ $url }})
</x-mail::subcopy>
</x-mail::message>