@props(['type', 'slug'])
{{-- Server-side follow (entitlement saved.server, up to watches.max): feeds the daily
     alert. At the plan's limit the button says so instead of failing on submit;
     guests are sent to sign in. --}}
@php($u = auth()->user())
@if($u && $u->entitled('saved.server'))
@php($following = \App\Models\Follow::where('user_id', $u->id)->where('subject_type', $type)->where('subject_slug', $slug)->exists())
@if(! $following && \App\Models\Follow::where('user_id', $u->id)->count() >= $u->watchLimit())
<a href="{{ route('pricing') }}" {{ $attributes->merge(['class' => 'btn-secondary']) }} title="You are watching {{ $u->watchLimit() }} items, the most your plan allows" data-track="follow_limit_click">Watch limit reached: see Pro</a>
@else
<form method="post" action="{{ route('follow.toggle', [$type, $slug]) }}" {{ $attributes }}>@csrf<button type="submit" class="btn-secondary w-full" aria-pressed="{{ $following ? 'true' : 'false' }}" title="{{ $following ? 'Stop daily alerts for this record' : 'Get a daily alert when this record changes' }}" data-track="{{ $following ? 'unfollow_record' : 'follow_record' }}">{{ $following ? 'Following ✓' : 'Follow for daily alerts' }}</button></form>
@endif
@else
<a href="{{ route('login') }}" {{ $attributes->merge(['class' => 'btn-secondary']) }} title="Sign in to get a daily alert when this record changes" data-track="follow_signin_click">Sign in to follow</a>
@endif
