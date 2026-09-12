@component('emails.site.layout', ['title' => 'Payment did not go through', 'unsubscribeUrl' => $unsubscribeUrl])
<p style="margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#006AAC;font-weight:600;">Billing</p>
<h1 style="font-family:'Space Grotesk',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">Your {{ $subscription->planName() }} renewal failed</h1>
<p style="margin:0 0 8px;">Hello {{ $user->name }}, the latest payment for your {{ $subscription->planName() }} plan was declined. Your access continues for {{ $graceDays }} days so you can update the payment method.</p>
<p style="margin:0 0 16px;">Open your account and choose "Manage billing" to update the card. If the payment is not completed within the grace period the plan returns to Free; nothing you saved is deleted.</p>
<p style="margin:0;"><a href="{{ $accountUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Open your account</a></p>
@endcomponent
