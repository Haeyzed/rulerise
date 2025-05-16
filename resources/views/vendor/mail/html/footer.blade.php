<tr>
<td>
<table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td class="content-cell" align="center">
<div class="social-media">
<a href="#"><img src="{{ asset('images/facebook-icon.svg') }}" alt="Facebook"></a>
<a href="#"><img src="{{ asset('images/twitter-icon.svg') }}" alt="Twitter"></a>
<a href="#"><img src="{{ asset('images/instagram-icon.svg') }}" alt="Instagram"></a>
</div>
<a href="#" style="display: block; margin-top: 15px; color: var(--blue_500); font-weight: 600; font-size: 14px; text-decoration: none;">Contact Us</a>
{{ Illuminate\Mail\Markdown::parse($slot) }}
<p style="margin-top: 10px;">Click <a href="#">here</a> to Unsubscribe</p>
</td>
</tr>
</table>
</td>
</tr>
