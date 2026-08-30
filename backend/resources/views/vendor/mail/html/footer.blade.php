@php
    $brandUrl = rtrim((string) (config('app.frontend_url') ?: config('app.url')), '/');
@endphp
<tr>
<td>
<table class="footer" align="center" width="580" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto; padding: 24px 0 32px; text-align: center; width: 100%; max-width: 580px;">
<tr>
<td class="content-cell" align="center" style="padding: 0 32px;">
    <p style="margin: 0 0 8px; font-size: 12px; color: #6e6668; line-height: 1.6;">
        Email này được gửi tự động từ hệ thống đặt sân thể thao <strong style="color: #1c1618;">Aura Court</strong>.
    </p>
    <p style="margin: 0 0 10px; font-size: 12px;">
        <a href="{{ $brandUrl }}" style="color: #6B2D3F; font-weight: 600; margin: 0 8px; text-decoration: none;">Trang chủ</a> ·
        <a href="{{ $brandUrl }}/courts" style="color: #6B2D3F; font-weight: 600; margin: 0 8px; text-decoration: none;">Danh sách sân</a> ·
        <a href="{{ $brandUrl }}/bookings" style="color: #6B2D3F; font-weight: 600; margin: 0 8px; text-decoration: none;">Lịch đặt sân</a> ·
        <a href="{{ $brandUrl }}/privacy" style="color: #6B2D3F; font-weight: 600; margin: 0 8px; text-decoration: none;">Chính sách bảo mật</a>
    </p>
    <p style="margin: 0; font-size: 11px; color: #8c8285;">
        © {{ date('Y') }} Aura Court Platform. Hệ thống sân thể thao & đặt lịch thông minh.
    </p>
</td>
</tr>
</table>
</td>
</tr>

