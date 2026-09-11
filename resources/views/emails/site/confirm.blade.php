@component('emails.site.layout', ['title' => 'Confirm your subscription', 'unsubscribeUrl' => $unsubscribeUrl])
<h1 style="font-family:Georgia,'Source Serif 4',serif;font-size:22px;color:#002147;margin:0 0 12px;">Confirm your AI policy digest</h1>
<p style="margin:0 0 16px;">One click and you will receive a weekly, source-linked summary of AI policy changes and upcoming application dates{{ ($subscriber->topics && !in_array('all', $subscriber->topics, true)) ? ' for '.implode(', ', $subscriber->topics) : '' }}.</p>
<p style="margin:0 0 20px;"><a href="{{ $confirmUrl }}" style="display:inline-block;background:#002147;color:#ffffff;text-decoration:none;padding:12px 20px;font-weight:600;">Confirm subscription</a></p>
<p style="margin:0;font-size:13px;color:#5D6B7E;">If you did not request this, ignore this email; nothing will be sent. Link: {{ $confirmUrl }}</p>
@endcomponent
