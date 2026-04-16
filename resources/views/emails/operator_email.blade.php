<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Operator Account Created</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        h1 {
            color: #22c55e;
            margin-bottom: 10px;
        }
        p {
            font-size: 15px;
            line-height: 1.6;
        }
        .credentials {
            background-color: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            font-size: 14px;
        }
        .button {
            display: inline-block;
            padding: 12px 20px;
            background-color: #22c55e;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
            font-weight: bold;
        }
        .warning {
            margin-top: 15px;
            font-size: 13px;
            color: #ef4444;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #999;
            text-align: center;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome to GoBus Tracker</h1>

    <p>Hi {{ $user->name ?? 'Operator' }},</p>

    <p>Your operator account has been successfully created. You can now log in using the credentials below:</p>

    <div class="credentials">
        <p><strong>Email:</strong> {{ $user->email }}</p>
        <p><strong>Password:</strong> {{ $password }}</p>
    </div>

    <a href="https://gobus-admin.netlify.app" class="button">
        Login to Dashboard
    </a>

    <p class="warning">
        ⚠ Please change your password after your first login for security.
    </p>

    <div class="footer">
        &copy; {{ date('Y') }} Bus Tracker System. All rights reserved.
    </div>
</div>

</body>
</html>