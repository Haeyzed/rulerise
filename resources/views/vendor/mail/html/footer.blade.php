<tr>
    <td>
        <table class="footer" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
            <tr>
                <td class="content-cell" align="center">
                    <div class="social-icons">
                        <a href="{{ config('mail.social.facebook', '#') }}" style="background: #718096; border-radius: 50%; color: white; display: inline-block; height: 30px; line-height: 30px; margin: 0 5px; text-align: center; text-decoration: none; width: 30px;">f</a>
                        <a href="{{ config('mail.social.twitter', '#') }}" style="background: #718096; border-radius: 50%; color: white; display: inline-block; height: 30px; line-height: 30px; margin: 0 5px; text-align: center; text-decoration: none; width: 30px;">𝕏</a>
                        <a href="{{ config('mail.social.instagram', '#') }}" style="background: #718096; border-radius: 50%; color: white; display: inline-block; height: 30px; line-height: 30px; margin: 0 5px; text-align: center; text-decoration: none; width: 30px;">📷</a>
                    </div>

                    @if(isset($contactUrl))
                        <p style="color: #26A4FF; font-size: 14px; font-weight: 600; margin: 10px 0;">
                            <a href="{{ $contactUrl }}" style="color: #26A4FF; text-decoration: none;">Contact Us</a>
                        </p>
                    @endif

                    <p style="color: #282828; font-size: 14px; margin: 10px 0;">
                        © {{ date('Y') }} {{ config('app.name', 'TalentBeyondBorders') }}. All rights reserved.
                    </p>

                    @if(isset($unsubscribeUrl))
                        <p style="color: #282828; font-size: 14px; margin: 10px 0;">
                            Click <a href="{{ $unsubscribeUrl }}" style="color: #282828; text-decoration: underline;">here</a> to Unsubscribe
                        </p>
                    @endif
                </td>
            </tr>
        </table>
    </td>
</tr>
