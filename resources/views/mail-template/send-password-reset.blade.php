<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Password Reset</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            border: 1px solid #dddddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        a {
            color: #007bff;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #555555;
        }
    </style>
</head>
<body>
    <div class="container">
        <p>Hello {{ $recipient }},</p>

        <p>{{ $emailMessage }}</p> <!-- Updated to use $emailMessage -->

        <p><a href="{{ $resetLink }}" aria-label="Reset your password">Reset Password</a></p>

        <p>If you did not request a password reset, no further action is required.</p>

        <p>Thank you,<br>
        {{ config('app.name', 'Your Application') }}</p>

        <div class="footer">
            <p>If you have any questions, please contact our support team.</p>
        </div>
    </div>
</body>
</html>
