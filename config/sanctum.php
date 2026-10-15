<?php

/*
| Only what differs from Sanctum's defaults; the package merges the rest.
|
| Token lifetime in minutes. Unset, tokens do not expire (the behaviour so far, so
| existing integrations keep working); set SANCTUM_EXPIRATION_MINUTES (for example
| 525600 for a year) to make every token, old and new, expire that long after it
| was created.
*/

return [
    'expiration' => env('SANCTUM_EXPIRATION_MINUTES') ? (int) env('SANCTUM_EXPIRATION_MINUTES') : null,
];
