@component('emails.site.layout', ['message' => $message ?? null, 'title' => 'Email address changed'])
<h1 style="font-family:-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:22px;color:#002147;margin:0 0 12px;">Your account's email address was changed</h1>
<p style="margin:0 0 16px;">Hello {{ $name }}, the email address on your AIPolicyTracker account was just changed to {{ $masked }}. Messages, including password resets, now go there.</p>
<p style="margin:0 0 16px;">If you made this change, there is nothing to do. If you did not, someone may have access to your account: tell us through the <a href="{{ $contactUrl }}" style="color:#006AAC;">contact form</a> and we will help you recover it.</p>
@endcomponent
