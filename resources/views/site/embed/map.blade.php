@php($body = view('site.embed._map-body', compact('tiles', 'quarter', 'totals'))->render())
@include('site.embed._frame', ['title' => 'Where AI is regulated', 'body' => $body, 'sourceUrl' => route('state-of.show')])
