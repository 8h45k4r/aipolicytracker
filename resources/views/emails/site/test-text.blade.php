{{ config('aipolicytracker.site_name') }} mail transport test

Sent {{ $sentAt->format('Y-m-d H:i') }} UTC via {{ $transport }}. Subscribers will receive confirmation, weekly digest and alert emails in the branded layout.

{{ url('/') }}
