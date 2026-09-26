{{-- Self-contained by design: when the application is the thing that failed, the
     site layout is not safe to render. See errors/minimal.blade.php. --}}
@include('errors.minimal', [
    'code' => '500',
    'title' => 'Something broke on our side',
    'body' => 'This is a fault in the site, not in anything you did. It has been logged. Nothing you were reading was changed or lost.',
    'detail' => 'The policy records themselves are published as open data, so if you need this information right now you can read it without the website: the JSON API at /api/v1, the full dataset at /open-data, or any record as a plain file at /policies/{slug}.md.',
    'reference' => \App\Http\Middleware\AssignRequestId::assign(request()),
])
