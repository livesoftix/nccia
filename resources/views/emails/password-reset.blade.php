<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 40px 20px;">
  <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 24px;">
      <h2 style="margin: 0; color: #015C94; font-size: 20px;">NCCIA Portal</h2>
      <p style="margin: 4px 0 0; color: #6c757d; font-size: 13px;">Password Reset Request</p>
    </div>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">Dear <strong>{{ $name }}</strong>,</p>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">We received a request to reset your portal password. Click the button below to choose a new password. This link expires in <strong>60 minutes</strong>.</p>
    <div style="text-align: center; margin: 28px 0;">
      <a href="{{ $resetUrl }}" style="display: inline-block; background: #015C94; color: #fff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px;">Reset Password</a>
    </div>
    <p style="font-size: 12px; color: #666; line-height: 1.5; word-break: break-all;">Or copy this link:<br>{{ $resetUrl }}</p>
    <p style="font-size: 12px; color: #999; line-height: 1.5; text-align: center; margin-top: 20px;">If you did not request a password reset, you can safely ignore this email.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 11px; color: #aaa; text-align: center;">National Cyber Crime Investigation Agency</p>
  </div>
</body>
</html>
