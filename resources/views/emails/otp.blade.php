<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>OTP Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f9f9f9;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: auto;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .otp-code {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
            letter-spacing: 4px;
            text-align: center;
            margin: 20px 0;
        }
        .footer {
            font-size: 12px;
            color: #888;
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>E-Commerce Website</h2>
        <p>Hello,</p>
        <p>Your OTP code for login is:</p>
        <div class="otp-code">{{ $otp }}</div>
        <p>This OTP will expire in 5 minutes. Do not share it with anyone.</p>
        <div class="footer">
            &copy; {{ date('Y') }} E-Commerce Website. All rights reserved.
        </div>
    </div>
</body>
</html>
