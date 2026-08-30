@props([
    'url',
    'color' => 'primary',
    'align' => 'center',
])
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin: 24px 0;">
<tr>
<td align="{{ $align }}">
<table width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="{{ $align }}">
<table border="0" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center" style="border-radius: 9999px; background-color: #6B2D3F; box-shadow: 0 6px 20px rgba(107, 45, 63, 0.35);">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: none; border-radius: 9999px; color: #ffffff; display: inline-block; overflow: hidden; text-decoration: none; background: linear-gradient(135deg, #6B2D3F 0%, #52212F 100%); background-color: #6B2D3F; padding: 14px 36px; font-weight: 750; font-size: 14.5px; letter-spacing: 0.02em;">{{ $slot }} &rarr;</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>

