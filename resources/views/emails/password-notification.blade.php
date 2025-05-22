<x-mail::message>
# Welcome to {{ config('app.name') }}

Hello {{ $user->first_name }},

You have been added as an admin member on {{ config('app.name') }}.

Here are your login credentials:

**Email:** {{ $user->email }}
**Password:** {{ $password }}

Please use these credentials to log in to your account. We recommend changing your password after your first login for security reasons.

<x-mail::button :url="$loginUrl">
Login to Your Account
</x-mail::button>

If you have any questions, please contact your administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
