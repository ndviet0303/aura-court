<!doctype html>
<html lang="vi" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }} · Aura Court</title>
    <!--[if mso]>
    <noscript>
        <xml>
            <o:OfficeDocumentSettings>
                <o:PixelsPerInch>96</o:PixelsPerInch>
            </o:OfficeDocumentSettings>
        </xml>
    </noscript>
    <![endif]-->
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #eff1f4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #191c1d; }
        a { color: #78555e; text-decoration: none; }
        .btn-primary:hover { background-color: #5d4249 !important; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#eff1f4;-webkit-font-smoothing:antialiased;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#eff1f4;padding:40px 16px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:580px;background-color:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 12px 40px rgba(0,0,0,0.06);border:1px solid #e0e4ea;">
                    
                    <!-- Header Banner with Aura Brand Colors -->
                    <tr>
                        <td style="padding:32px 36px 28px;background:linear-gradient(135deg, #ffd1dc 0%, #ffd9e2 50%, #fbe8ec 100%);border-bottom:1px solid rgba(120,85,94,0.12);">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:48px;height:48px;background-color:#ffffff;border-radius:14px;text-align:center;vertical-align:middle;box-shadow:0 4px 12px rgba(120,85,94,0.15);">
                                                    <img src="{{ $logoUrl }}" width="30" height="30" alt="Aura Court" style="display:inline-block;vertical-align:middle;border-radius:6px;">
                                                </td>
                                                <td style="padding-left:14px;vertical-align:middle;">
                                                    <div style="font-size:22px;font-weight:800;letter-spacing:-0.03em;color:#191c1d;line-height:1.2;">Aura Court</div>
                                                    <div style="font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#78555e;margin-top:2px;">Nền tảng Đặt sân & Thể thao Đa môn</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block;padding:5px 12px;background:rgba(120,85,94,0.12);color:#78555e;border-radius:9999px;font-size:11px;font-weight:700;letter-spacing:0.04em;">
                                            THÔNG BÁO
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:36px 36px 32px;">
                            <h1 style="margin:0 0 18px;font-size:24px;font-weight:800;letter-spacing:-0.03em;color:#191c1d;line-height:1.3;">
                                {{ $title }}
                            </h1>

                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#333a40;">
                                Xin chào <strong>{{ $user->name ?? 'bạn' }}</strong>,
                            </p>

                            <!-- Message Content Box -->
                            <div style="margin:0 0 28px;padding:20px 22px;background-color:#f8f9fa;border-radius:16px;border:1px solid #e7e8e9;font-size:15px;line-height:1.75;color:#2c3036;">
                                {!! nl2br(e($bodyText)) !!}
                            </div>

                            <!-- Action Button -->
                            @if(!empty($actionUrl))
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 28px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="border-radius:9999px;background-color:#78555e;box-shadow:0 4px 16px rgba(120,85,94,0.3);">
                                                    <a href="{{ $actionUrl }}" target="_blank" class="btn-primary" style="display:inline-block;padding:14px 36px;font-size:14px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:9999px;letter-spacing:0.02em;">
                                                        {{ $actionText ?? 'Mở Aura Court' }} &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            @endif

                            <p style="margin:0 0 6px;font-size:13px;color:#5d5f5f;line-height:1.6;">
                                Nếu bạn có bất kỳ câu hỏi nào, hãy liên hệ trực tiếp với chúng tôi qua hotline hoặc email hỗ trợ.
                            </p>
                            <p style="margin:0;font-size:13px;color:#8a8d8d;line-height:1.6;">
                                Trân trọng,<br>
                                <strong style="color:#191c1d;">Đội ngũ Aura Court</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Fallback Link if Button Fails -->
                    @if(!empty($actionUrl))
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <div style="padding:14px 18px;background:#f5f6f8;border-radius:12px;border:1px dashed #d8dce2;font-size:12px;color:#787b80;line-height:1.6;word-break:break-all;">
                                Nếu nút phía trên không hoạt động, bạn có thể sao chép liên kết sau dán vào trình duyệt:<br>
                                <a href="{{ $actionUrl }}" style="color:#78555e;font-weight:600;text-decoration:underline;">{{ $actionUrl }}</a>
                            </div>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 36px 28px;background-color:#f8f9fa;border-top:1px solid #edf0f2;text-align:center;">
                            <p style="margin:0 0 10px;font-size:12px;color:#787b80;line-height:1.6;">
                                Email này được gửi tự động từ hệ thống đặt sân thể thao <strong style="color:#191c1d;">Aura Court</strong>.
                            </p>
                            <p style="margin:0 0 14px;font-size:12px;">
                                <a href="{{ $frontendUrl ?? 'https://court.ziet.dev' }}" style="color:#78555e;font-weight:600;margin:0 8px;text-decoration:none;">Trang chủ</a> ·
                                <a href="{{ ($frontendUrl ?? 'https://court.ziet.dev') . '/courts' }}" style="color:#78555e;font-weight:600;margin:0 8px;text-decoration:none;">Danh sách sân</a> ·
                                <a href="{{ ($frontendUrl ?? 'https://court.ziet.dev') . '/bookings' }}" style="color:#78555e;font-weight:600;margin:0 8px;text-decoration:none;">Lịch đặt sân</a> ·
                                <a href="{{ ($frontendUrl ?? 'https://court.ziet.dev') . '/privacy' }}" style="color:#78555e;font-weight:600;margin:0 8px;text-decoration:none;">Chính sách bảo mật</a>
                            </p>
                            <p style="margin:0;font-size:11px;color:#9ca3af;">
                                &copy; {{ date('Y') }} Aura Court Platform. Hệ thống sân thể thao & đặt lịch thông minh.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
