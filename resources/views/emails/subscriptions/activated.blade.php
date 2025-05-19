@component('mail::message')
# Subscription Activated

Dear {{ $user->first_name }} {{ $user->last_name }},

Congratulations! You have successfully subscribed to our "{{ $plan->name }}" premium CV plan.

Your card has been charged a sum of {{ strtoupper($subscription->currency) }}{{ $subscription->amount_paid }}.

## Subscription Details

@component('mail::panel')
**Plan:** {{ $plan->name }}  
**CV Download Access:** {{ $plan->resume_views_limit === 999999 ? 'Unlimited' : $plan->resume_views_limit }}  
**Package Type:** {{ $isOneTime ? 'One-time Payment' : 'Monthly Recurring' }}  
@if($isRecurring && $nextBillingDate)
**Next Billing:** {{ $nextBillingDate }}  
@endif
@if($paymentMethod)
**Payment Method:** {{ $paymentMethod }}{{ $lastFour ? ' - '.$lastFour : '' }}  
@endif
@endcomponent

@if($isRecurring)
## Cancellation Instructions

To cancel Subscription Auto-renewal:

1. Log in to your employer profile account.
2. Select Manage Subscription on your dashboard
3. Turn off Auto-Renewal
4. Your subscription will not be charged automatically on your debit card after this current plan expires

@endif

If you do not recognize this transaction or want to cancel these charges, please contact us.

@component('mail::button', ['url' => $url])
View Subscription
@endcomponent

Thank you for using our platform!

Regards,  
{{ config('app.name') }}
@endcomponent
