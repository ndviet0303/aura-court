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
<td align="center" style="border-radius: 9999px; background-color: #78555e; box-shadow: 0 4px 16px rgba(120,85,94,0.3);">
<a href="{{ $url }}" class="button button-{{ $color }}" target="_blank" rel="noopener" style="box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-text-size-adjust: none; border-radius: 9999px; color: #ffffff; display: inline-block; overflow: hidden; text-decoration: none; background-color: #78555e; padding: 14px 34px; font-weight: 700; font-size: 14px; letter-spacing: 0.02em;">{{ $slot }} &rarr;</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
</td>
</tr>
</table>
