<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đặt lại mật khẩu · Aura Court</title>
</head>
<body style="margin:0;background:#f8f9fa;color:#191c1d;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 16px;background:#f8f9fa;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border:1px solid #e7e8e9;border-radius:22px;overflow:hidden;">
            <tr><td style="padding:28px 32px;background:#ffd1dc;">
                <img src="{{ $logoUrl }}" width="42" height="42" alt="Aura Court" style="display:block;border-radius:12px;margin-bottom:18px;">
                <div style="font-size:22px;font-weight:700;letter-spacing:-.04em;color:#191c1d;">Aura Court</div>
                <div style="margin-top:6px;color:#78555e;font-size:12px;letter-spacing:.16em;text-transform:uppercase;">Đặt sân cầu lông</div>
            </td></tr>
            <tr><td style="padding:34px 32px 38px;">
                <h1 style="margin:0 0 16px;font-size:28px;letter-spacing:-.04em;">Đặt lại mật khẩu</h1>
                <p style="margin:0 0 16px;line-height:1.7;color:#5d5f5f;">Xin chào {{ $user->name }},</p>
                <p style="margin:0 0 24px;line-height:1.7;color:#5d5f5f;">Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản Aura Court của bạn. Nhấn nút bên dưới để tạo mật khẩu mới.</p>
                <p style="margin:0 0 28px;"><a href="{{ $url }}" style="display:inline-block;padding:14px 22px;border-radius:999px;background:#78555e;color:#ffffff;text-decoration:none;font-weight:700;">Đặt lại mật khẩu</a></p>
                <p style="margin:0 0 12px;line-height:1.6;color:#5d5f5f;font-size:13px;">Liên kết này sẽ hết hạn sau {{ $expires }} phút.</p>
                <p style="margin:0;line-height:1.6;color:#5d5f5f;font-size:13px;">Nếu bạn không yêu cầu thay đổi mật khẩu, có thể bỏ qua email này.</p>
            </td></tr>
            <tr><td style="padding:18px 32px;border-top:1px solid #edf0f1;color:#8a8d8d;font-size:12px;">© {{ date('Y') }} Aura Court · Đặt sân cầu lông.</td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
