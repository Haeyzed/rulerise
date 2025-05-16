@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo.png" class="logo" alt="Laravel Logo">
@elseif (trim($slot) === 'TalentBeyondBorders')
<img src="{{ asset('images/talent-beyond-borders-logo.png') }}" class="logo" alt="TalentBeyondBorders Logo">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
