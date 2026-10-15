{{ config('aipolicytracker.site_name') }}: second-factor sign-in locked

{{ $failures }} wrong authenticator codes were entered for your admin account, so authenticator codes are refused for the next {{ $hours }} hours (from {{ $lockedAt->format('Y-m-d H:i') }} UTC). A one-time recovery code still works.

The second-factor step only appears after the correct password. If this was not you, change your password now and tell the site owner: {{ route('password.request') }}
