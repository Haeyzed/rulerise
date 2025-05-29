<x-mail::message>

<div style="text-align: center; margin-bottom: 40px;">
<div style="background: rgba(113, 128, 150, 0.05); border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin: 0 auto 20px; width: 120px; height: 120px;">
<div style="background: rgba(113, 128, 150, 0.06); border-radius: 50%; display: flex; align-items: center; justify-content: center; width: 80px; height: 80px;">
<svg width="40" height="32" viewBox="0 0 40 32" fill="none" xmlns="http://www.w3.org/2000/svg">
<path d="M36 0H4C1.8 0 0.02 1.8 0.02 4L0 28C0 30.2 1.8 32 4 32H36C38.2 32 40 30.2 40 28V4C40 1.8 38.2 0 36 0ZM36 8L20 18L4 8V4L20 14L36 4V8Z" fill="#718096"/>
<circle cx="20" cy="12" r="3" fill="white"/>
</svg>
</div>
</div>

<div style="background: #718096; height: 2px; margin: 20px auto; width: 100px;"></div>
</div>

# Verify Your Email

<div style="background: #26A4FF; height: 2px; margin: 10px auto 30px; width: 40px;"></div>

Welcome! Please click the button below to verify your email address.

<x-mail::button :url="$url" color="primary">
VERIFY EMAIL ADDRESS
</x-mail::button>

If you did not create an account, no further action is required.

Best regards,
{{ config('app.name') }} Team

---

If you're having trouble clicking the "Verify Email Address" button, copy and paste the URL below into your web browser:

<div style="word-break: break-all; font-size: 12px; color: #666;">
{{ $url }}
</div>

</x-mail::message>
