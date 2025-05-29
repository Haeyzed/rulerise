<tr>
    <td class="header">
        <a href="{{ $url }}" style="display: inline-block;">
            <div style="text-align: center; padding: 20px 0;">
                <div style="display: inline-flex; align-items: center; gap: 10px;">
                    <div style="width: 40px; height: 40px; background: #26A4FF; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 17L12 22L22 17" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="white" stroke-width="2" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span style="font-family: 'Outfit', Arial, sans-serif; font-size: 20px; font-weight: 600; color: #26A4FF;">
                        {{ $slot ?? config('app.name') }}
                    </span>
                </div>
            </div>
        </a>
    </td>
</tr>
