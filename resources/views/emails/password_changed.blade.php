<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Password Changed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            color: #333333;
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
        }
        p {
            font-size: 16px;
            line-height: 1.5;
        }
        .footer {
            margin-top: 30px;
            font-size: 12px;
            color: #999999;
            text-align: center;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #22c55e;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 8px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Password Changed Successfully</h1>
        <p>Hi {{ $user->name ?? 'User' }},</p>
        <p>This is a confirmation that your password for your Bus Tracker account has been changed successfully.</p>
        <p>If you did not request this change, please contact our support immediately.</p>

        <div class="footer">
            &copy; {{ date('Y') }} Bus Tracker System. All rights reserved.
        </div>
    </div>
</body>
</html>
