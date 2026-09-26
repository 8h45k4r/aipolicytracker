@php($body = view('site.embed._deadlines-body', compact('deadlines', 'j'))->render())
@include('site.embed._frame', ['title' => 'Upcoming AI regulation deadlines', 'body' => $body, 'sourceUrl' => route('calendar')])
