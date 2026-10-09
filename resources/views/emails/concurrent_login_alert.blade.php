<!DOCTYPE html>
<html>
<head>
    <title>Security Alert</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <h2 style="color: #d9534f;">Security Alert: Unauthorized Login Attempt</h2>
    <p>Dear {{ $userName }},</p>
    <p>We detected a login attempt to your NCCIA account while you were already logged in from another device or session.</p>
    <p>For your security, this new login attempt has been <strong>blocked</strong>.</p>
    <ul>
        <li><strong>Attempted IP Address:</strong> {{ $ipAddress }}</li>
        <li><strong>Time:</strong> {{ $time }}</li>
    </ul>
    <p>If this was you, please log out from your other device first before logging in here.</p>
    <p>If this was not you, someone might know your password. Please change your password immediately and contact the system administrator.</p>
    <br>
    <p>Regards,<br>NCCIA Security System</p>
</body>
</html>
