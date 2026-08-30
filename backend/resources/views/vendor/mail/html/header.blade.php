@props(['url'])
@php
    $rawFrontend = (string) (config('app.frontend_url') ?: config('app.url') ?: 'https://court.ziet.dev');
    $brandUrl = (str_contains($rawFrontend, 'localhost') || str_contains($rawFrontend, '127.0.0.1'))
        ? 'https://court.ziet.dev'
        : rtrim($rawFrontend, '/');
    $logoSrc = $brandUrl . '/aura/badge-logo.png';
@endphp
<tr>
<td class="header" style="padding: 28px 32px 22px; background: linear-gradient(135deg, #4A1D2B 0%, #6B2D3F 60%, #7D3549 100%); border-radius: 24px 24px 0 0; text-align: left; border-bottom: 1px solid rgba(201,161,90,0.35);">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
            <td style="vertical-align: middle; width: 44px;">
                <a href="{{ $brandUrl }}" style="display: block; width: 44px; height: 44px; background: #ffffff; border-radius: 12px; text-align: center; line-height: 44px; box-shadow: 0 4px 14px rgba(0,0,0,0.25); border: 1px solid rgba(201,161,90,0.4); text-decoration: none;">
                    <img src="{{ $logoSrc }}" width="28" height="28" alt="Aura Court" style="display: inline-block; vertical-align: middle; border-radius: 6px; border: none; margin-top: 8px;">
                </a>
            </td>
            <td style="vertical-align: middle; padding-left: 14px; text-align: left;">
                <a href="{{ $brandUrl }}" style="text-decoration: none; display: block;">
                    <div style="font-size: 20px; font-weight: 800; letter-spacing: -0.03em; color: #ffffff; line-height: 1.2;">Aura Court</div>
                    <div style="font-size: 11px; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: #E58E9F; margin-top: 2px;">Nền tảng Đặt sân & Thể thao Đa môn</div>
                </a>
            </td>
            <td align="right" style="vertical-align: middle;">
                <span style="display: inline-block; padding: 4px 12px; background: rgba(223,186,114,0.18); color: #DFBA72; border: 1px solid rgba(223,186,114,0.4); border-radius: 9999px; font-size: 11px; font-weight: 800; letter-spacing: 0.06em;">
                    THÔNG BÁO
                </span>
            </td>
        </tr>
    </table>
</td>
</tr>

