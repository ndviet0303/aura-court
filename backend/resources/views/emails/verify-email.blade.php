<!doctype html>
<html lang="vi" xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác thực địa chỉ Email · Aura Court</title>
    <style>
        body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
        img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse !important; }
        body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; background-color: #f5f2eb; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; color: #1c1618; }
        a { color: #6B2D3F; text-decoration: none; }
        .btn-primary:hover { background-color: #52212F !important; }
    </style>
</head>
<body style="margin:0;padding:0;background-color:#f5f2eb;-webkit-font-smoothing:antialiased;">
    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color:#f5f2eb;padding:40px 16px;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="max-width:580px;background-color:#ffffff;border-radius:24px;overflow:hidden;box-shadow:0 12px 40px rgba(107,45,63,0.08);border:1px solid #ebdccb;">
                    
                    <!-- Header Banner -->
                    <tr>
                        <td style="padding:32px 36px 28px;background:linear-gradient(135deg, #4A1D2B 0%, #6B2D3F 60%, #7D3549 100%);border-bottom:1px solid rgba(201,161,90,0.35);">
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td style="vertical-align:middle;">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td style="width:48px;height:48px;background-color:#ffffff;border-radius:14px;text-align:center;vertical-align:middle;box-shadow:0 4px 14px rgba(0,0,0,0.25);border:1px solid rgba(201,161,90,0.4);overflow:hidden;">
                                                    <img src="{{ $logoUrl }}" width="32" height="32" alt="AC" style="display:block;margin:0 auto;border:none;border-radius:6px;max-width:32px;max-height:32px;">
                                                </td>
                                                <td style="padding-left:14px;vertical-align:middle;">
                                                    <div style="font-size:22px;font-weight:800;letter-spacing:-0.03em;color:#ffffff;line-height:1.2;">Aura Court</div>
                                                    <div style="font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#E58E9F;margin-top:2px;">Nền tảng Đặt sân & Thể thao Đa môn</div>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                    <td align="right" style="vertical-align:middle;">
                                        <span style="display:inline-block;padding:5px 14px;background:rgba(223,186,114,0.18);color:#DFBA72;border:1px solid rgba(223,186,114,0.4);border-radius:9999px;font-size:11px;font-weight:800;letter-spacing:0.06em;">
                                            XÁC THỰC
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding:36px 36px 32px;">
                            <h1 style="margin:0 0 18px;font-size:24px;font-weight:800;letter-spacing:-0.03em;color:#1c1618;line-height:1.3;">
                                Xác thực tài khoản của bạn
                            </h1>

                            <p style="margin:0 0 16px;font-size:15px;line-height:1.7;color:#333a40;">
                                Xin chào <strong>{{ $user->name ?? 'bạn' }}</strong>,
                            </p>

                            <div style="margin:0 0 28px;padding:20px 22px;background-color:#FAF7F3;border-radius:16px;border:1px solid #ebdccb;font-size:15px;line-height:1.75;color:#2c2024;">
                                Cảm ơn bạn đã đăng ký tài khoản tại <strong>Aura Court</strong>. Vui lòng bấm vào nút bên dưới để hoàn tất xác thực địa chỉ email và kích hoạt đầy đủ quyền lợi đặt sân.
                            </div>

                            <!-- Action Button -->
                            <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%" style="margin:0 0 28px;">
                                <tr>
                                    <td align="center">
                                        <table role="presentation" border="0" cellpadding="0" cellspacing="0">
                                            <tr>
                                                <td align="center" style="border-radius:9999px;background-color:#6B2D3F;box-shadow:0 6px 20px rgba(107,45,63,0.35);">
                                                    <a href="{{ $url }}" target="_blank" class="btn-primary" style="display:inline-block;padding:14px 36px;font-size:14.5px;font-weight:750;color:#ffffff;text-decoration:none;border-radius:9999px;letter-spacing:0.02em;background:linear-gradient(135deg, #6B2D3F 0%, #52212F 100%);">
                                                        Xác thực Email ngay &rarr;
                                                    </a>
                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0 0 8px;font-size:13px;color:#6e6668;line-height:1.6;">
                                Nếu bạn không thực hiện đăng ký tài khoản này, vui lòng bỏ qua email.
                            </p>
                            <p style="margin:0;font-size:13px;color:#8c8285;line-height:1.6;">
                                Trân trọng,<br>
                                <strong style="color:#1c1618;">Đội ngũ Aura Court</strong>
                            </p>
                        </td>
                    </tr>

                    <!-- Fallback Link -->
                    <tr>
                        <td style="padding:0 36px 28px;">
                            <div style="padding:14px 18px;background:#FAF7F3;border-radius:12px;border:1px dashed #dcd3c5;font-size:12px;color:#6e6668;line-height:1.6;word-break:break-all;">
                                Nếu nút phía trên không hoạt động, bạn có thể sao chép liên kết sau dán vào trình duyệt:<br>
                                <a href="{{ $url }}" style="color:#6B2D3F;font-weight:600;text-decoration:underline;">{{ $url }}</a>
                            </div>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="padding:24px 36px 28px;background-color:#FAF7F3;border-top:1px solid #ebdccb;text-align:center;">
                            <p style="margin:0 0 10px;font-size:12px;color:#6e6668;line-height:1.6;">
                                Email này được gửi tự động từ hệ thống đặt sân thể thao <strong style="color:#1c1618;">Aura Court</strong>.
                            </p>
                            <p style="margin:0 0 14px;font-size:12px;">
                                <a href="{{ $frontendUrl ?? 'https://court.ziet.dev' }}" style="color:#6B2D3F;font-weight:600;margin:0 8px;text-decoration:none;">Trang chủ</a> ·
                                <a href="{{ ($frontendUrl ?? 'https://court.ziet.dev') . '/courts' }}" style="color:#6B2D3F;font-weight:600;margin:0 8px;text-decoration:none;">Danh sách sân</a> ·
                                <a href="{{ ($frontendUrl ?? 'https://court.ziet.dev') . '/privacy' }}" style="color:#6B2D3F;font-weight:600;margin:0 8px;text-decoration:none;">Chính sách bảo mật</a>
                            </p>
                            <p style="margin:0;font-size:11px;color:#8c8285;">
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
