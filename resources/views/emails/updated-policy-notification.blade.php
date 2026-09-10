<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>New AI Policy Tracker</title>
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Poppins:ital,wght@100;400;500;700&display=swap");

        body {
            background-color: #f3f1f9;
            font-family: "Poppins", sans-serif;
            font-weight: 500;
            font-style: normal;
            margin: 0;
            padding: 0;
        }

        .container {
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #f3f1f9;
        }

        .content {
            background-color: #ffffff;
            border-radius: 8px;
            padding: 16px;
            width: 90%;
            max-width: 600px;
            margin: 16px;
        }

        .logo {
            display: block;
            margin: 0 auto;
            width: 127px;
            height: 33px;
            max-width: 100%;
            height: auto;
        }

        .heading {
            font-weight: 700;
            margin-top: 16px;
            font-size: 16px;
            text-align: center;
        }

        .divider {
            background-color: #e0f2f1;
            height: 2px;
            border-radius: 50px;
            margin: 16px 0;
        }

        .text {
            color: #9e9e9e;
            font-weight: 600;
            font-style: italic;
            margin-top: 16px;
            text-align: center;
        }

        .link {
            display: inline-block;
            padding: 10px 20px;
            font-size: 16px;
            color: #ffffff;
            text-decoration: none;
            border-radius: 5px;
            background-color: #003366;
            text-align: center;
            margin-top: 16px;
            display: block;
        }

        .footer {
            margin-top: 16px;
            text-align: center;
            color: #9e9e9e;
            font-weight: 600;
        }

        .footer p {
            margin: 4px 0;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="content">
            <div style="text-align: center;">
                <img src="{{ $message->embed($logo) }}" alt="AI Policy Tracker Logo" class="logo" />
            </div>
            <h1 class="heading">Hi, {{ $userName }}</h1>
            <h1 class="heading">Welcome to AI Policy Trackers!</h1>
            <div class="divider"></div>
            <p class="text">
                {{ $messageData ?? 'no' }}
            </p>
            <p class="text">Policy Name: {{ $trackerName }}</p>
            <div style="text-align: center;">
                <a href="{{ $action }}" class="link">View</a>
            </div>
            <div class="divider"></div>
            <div class="footer">
                <p>Thank you for staying updated.</p>
                <p>Regards,</p>
                <p>AI Policy Tracker</p>
            </div>
        </div>
    </div>
</body>

</html>
