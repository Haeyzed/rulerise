<x-mail::message>

<div style="text-align: center; margin-bottom: 40px;">
<div style="background: rgba(113, 128, 150, 0.05); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 20px; width: 120px; height: 120px;">
    <div style="background: rgba(113, 128, 150, 0.06); border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 80px; height: 80px;">
        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M30 13.33H28.33V10C28.33 5.4 24.6 1.67 20 1.67C15.4 1.67 11.67 5.4 11.67 10V13.33H10C8.17 13.33 6.67 14.83 6.67 16.67V33.33C6.67 35.17 8.17 36.67 10 36.67H30C31.83 36.67 33.33 35.17 33.33 33.33V16.67C33.33 14.83 31.83 13.33 30 13.33ZM20 28.33C18.17 28.33 16.67 26.83 16.67 25C16.67 23.17 18.17 21.67 20 21.67C21.83 21.67 23.33 23.17 23.33 25C23.33 26.83 21.83 28.33 20 28.33ZM25 13.33H15V10C15 7.23 17.23 5 20 5C22.77 5 25 7.23 25 10V13.33Z" fill="#718096"/>
        </svg>
    </div>
</div>

<div style="background: #718096; height: 2px; margin: 20px auto; width: 100px;"></div>
</div>

# Reset Your Password

<div style="background: #26A4FF; height: 2px; margin: 10px auto 30px; width: 40px;"></div>

Hello {{ $name ?? 'there' }},

You are receiving this email because we received a password reset request for your account.

<x-mail::button :url="$url" color="primary">
RESET PASSWORD
</x-mail::button>

This password reset link will expire in {{ $count ?? 60 }} minutes.

If you did not request a password reset, no further action is required.

Best regards,
{{ config('app.name') }} Team

---

If you're having trouble clicking the "Reset Password" button, copy and paste the URL below into your web browser:

<div style="word-break: break-all; font-size: 12px; color: #666;">
{{ $url }}
</div>

</x-mail::message>
