<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Your Verification Code (Expires in 5 mins)</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f4f6f8; padding: 20px;">

    <div style="max-width: 500px; margin: auto; background: #ffffff; padding: 20px; border-radius: 8px;">
        
        <h2 style="text-align: center; color: #333;">
            Email Verification
        </h2>

        <p style="color: #555; font-size: 14px;">
            Hi <strong>{{ $user->name }}</strong>,
        </p>

        <p style="color: #555; font-size: 14px;">
            Use the OTP below to verify your account:
        </p>

        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; letter-spacing: 5px; font-weight: bold; color: #2d89ef;">
                {{ $otp }}
            </span>
        </div>

        <p style="color: #777; font-size: 13px; text-align: center;">
            This code will expire in <strong>5 minutes</strong>.
        </p>

        <p style="color: #999; font-size: 12px; text-align: center;">
            If you didn’t request this, you can safely ignore this email.
        </p>

        <hr style="margin: 20px 0;">

        <p style="text-align: center; font-size: 12px; color: #aaa;">
            © {{ date('Y') }} Your App Name
        </p>

    </div>

</body>
</html>