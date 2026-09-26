@php($body = view('site.embed._jurisdiction-body', compact('j', 'policies', 'counts'))->render())
@include('site.embed._frame', ['title' => 'AI regulation in '.$j->name, 'body' => $body, 'sourceUrl' => $j->url()])
