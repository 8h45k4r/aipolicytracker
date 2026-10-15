<?php

/*
| Only what differs from Sanctum's defaults; the package merges the rest.
|
| Token lifetime in minutes, counted from each token's creation and applied to old
| tokens too. Unset, a token lasts a year (525600); set SANCTUM_EXPIRATION_MINUTES
| to change that, or to 0 (or empty) for tokens that last until revoked.
*/

return [
    'expiration' => (int) env('SANCTUM_EXPIRATION_MINUTES', 525600) ?: null,
];
