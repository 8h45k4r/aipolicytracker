Your {{ $subscription->planName() }} renewal failed

Hello {{ $user->name }}, the latest payment for your {{ $subscription->planName() }} plan was declined. Your access continues for {{ $graceDays }} days so you can update the payment method.

Open your account and choose "Manage billing" to update the card: {{ $accountUrl }}

If the payment is not completed within the grace period the plan returns to Free; nothing you saved is deleted.
