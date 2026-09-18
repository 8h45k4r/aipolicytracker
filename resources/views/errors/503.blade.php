{{-- Self-contained: shown while the app is down for maintenance, when nothing
     that depends on the application booting can be relied on. --}}
@include('errors.minimal', [
    'code' => '503',
    'title' => 'Down for maintenance',
    'body' => 'The site is being updated and will be back shortly. This is planned work, not a fault.',
    'detail' => 'Deployments normally take under a minute. The open dataset and the JSON API return with the site.',
])
