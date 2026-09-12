@props(['type', 'slug'])
{{-- Server-side follow (Pro, entitlement saved.server): feeds the daily alert. Free and guest visitors are pointed at the pricing page. --}}
@php($u = auth()->user())
@if($u && $u->entitled('saved.server'))
@php($following = \App\Models\Follow::where('user_id', $u->id)->where('subject_type', $type)->where('subject_slug', $slug)->exists())
<form method="post" action="{{ route('follow.toggle', [$type, $slug]) }}" {{ $attributes }}>@csrf<button type="submit" class="btn-secondary w-full" aria-pressed="{{ $following ? 'true' : 'false' }}" title="{{ $following ? 'Stop daily alerts for this record' : 'Get a daily alert when this record changes' }}" data-track="{{ $following ? 'unfollow_record' : 'follow_record' }}">{{ $following ? 'Following ✓' : 'Follow for daily alerts' }}</button></form>
@else
<a href="{{ route('pricing') }}" {{ $attributes->merge(['class' => 'btn-secondary']) }} title="Daily alerts for the records you follow are part of Pro" data-track="follow_upsell_click">Follow with Pro</a>
@endif
