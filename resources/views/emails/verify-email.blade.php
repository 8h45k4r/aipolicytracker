<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        /* Add styles for media queries where supported */
        @media only screen and (max-width: 600px) {
            .container {
                width: 100% !important;
            }

            .email-content {
                padding: 10px !important;
            }

            .heading {
                font-size: 20px !important;
            }

            .button {
                padding: 8px 16px !important;
                font-size: 14px !important;
            }
        }
    </style>
</head>

<body style="font-family: 'Poppins', sans-serif; background-color: #f3f1f9; margin: 0; padding: 0;">
    <div class="container"
        style="width: 100%; display: flex; align-items: center; justify-content: center; height: 100vh; padding: 0;">
        <div>
            <div style="text-align: center; margin-bottom:15px;">
                <img src="{{ asset('ai_dark_logo.png') }}" alt="" style="width: 127px; height: 33px;" />
            </div>
            <div class="email-content"
                style="background-color: #ffffff; border-radius: 8px; padding: 16px; max-width: 600px; width: 100%;">
                <p class="heading"
                    style="color: #1e3a8a; font-weight: bold; font-size: 24px; margin-top: 20px; text-align: center; margin-bottom: 20px; font-family: 'Poppins', sans-serif;">
                    Verify your email address</p>

                <div style="background-color: #bfdbfe; height: 2px; border-radius: 4px; margin: 20px 0;"></div>
                <p
                    style="color: #9ca3af; font-weight: semi-bold; text-align: center; font-family: 'Poppins', sans-serif;">
                    Please click the button below to verify your email address.</p>

                <a href="{{ $verificationUrl ?? '' }}"
                    style="display: inline-block; padding: 10px 20px; font-size: 16px; color: #ffffff; background-color: #1e3a8a; text-decoration: none; border-radius: 5px; text-align: center; margin-top: 20px; display: block; width: fit-content; margin-left: auto; margin-right: auto; font-family: 'Poppins', sans-serif;">Verify
                    Email Address</a>

                <div
                    style="background-color: #bfdbfe; height: 2px; border-radius: 4px; width: 130px; margin: 20px auto;">
                </div>

                <p
                    style="color: #9ca3af; font-weight: semi-bold; text-align: center; font-family: 'Poppins', sans-serif;">
                    If the button above is not clickable, please copy and paste the following link into the address bar
                    of your browser.
                </p>

                <p style="margin-bottom: 10px;">
                    <small>
                        <a href="{{ $verificationUrl ?? '' }}" style="color: #ADD8E6; word-wrap: break-word;">{{ $verificationUrl ?? '' }}</a>
                    </small>
                </p>


            </div>
        </div>
    </div>
</body>

</html>
