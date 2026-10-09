<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; background: #f4f4f4; padding: 40px 20px;">
  <div style="max-width: 480px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 4px 16px rgba(0,0,0,0.08);">
    <div style="text-align: center; margin-bottom: 24px;">
      <h2 style="margin: 0; color: #015C94; font-size: 20px;">NCCIA Portal</h2>
      <p style="margin: 4px 0 0; color: #6c757d; font-size: 13px;">One-Time Password Verification</p>
    </div>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">Dear <strong>{{ $name }}</strong>,</p>
    <p style="font-size: 14px; color: #333; line-height: 1.6;">Use the following OTP to complete your portal access setup. This OTP is valid for <strong>10 minutes</strong>.</p>
    <div style="text-align: center; margin: 28px 0; padding: 20px; background: #f8f9fa; border-radius: 8px; letter-spacing: 8px; font-size: 32px; font-weight: 700; color: #015C94; font-family: monospace;">
      {{ $otp }}
    </div>
    <p style="font-size: 12px; color: #999; line-height: 1.5; text-align: center;">If you did not request this OTP, please ignore this email.</p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 24px 0;">
    <p style="font-size: 11px; color: #aaa; text-align: center;">National Cyber Crime Investigation Agency</p>
  </div>
</body>
</html>
