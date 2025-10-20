<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            font-family: Arial, sans-serif;
        }
        .email-container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
            padding: 30px;
        }
        .header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        .header h1 {
            margin: 0;
            color: #007bff;
            font-size: 24px;
        }
        .content {
            padding: 20px 0;
            color: #333333;
            font-size: 16px;
            line-height: 1.6;
        }
        .otp-code {
            text-align: center;
            font-size: 32px;
            font-weight: bold;
            color: #28a745;
            margin: 20px 0;
            letter-spacing: 4px;
        }
        .footer {
            font-size: 12px;
            color: #888888;
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>OTP Verification</h1>
        </div>
        <div class="content">
            <p>Dear Customer,</p>
            <p>Your One-Time Password (OTP) for verification is:</p>
            <div class="otp-code">{{ $otp }}</div>
            <p>Please enter this code to complete your verification process. This code will expire after {{ env('WEBSITE_CUSTOMER_OTP_EXPIRE_MINUTES', 2) }} minutes.</p>
            <p>If you did not request this, please ignore this email.</p>
        </div>
        <div class="footer">
            &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
        </div>
    </div>
</body>
</html>
