@extends('site.layouts.app')
@section('content')
<div class="container-site py-8 max-w-3xl">
    <x-site.breadcrumbs :items="$seo->breadcrumbs" />
    <p class="eyebrow mt-3">Daily alerts</p>
    <h1 class="mt-2 text-2xl sm:text-3xl font-semibold tracking-tight text-brand-navy">Records you follow</h1>
    <p class="mt-3 text-brand-body leading-7">Every morning we check the policies, jurisdictions and obligations below. When a dated, source-linked change is recorded, or an application date is 30, 7 or 1 days away, you get one email at {{ $user->email }}. Quiet days send nothing.</p>
    @if(session('status') === 'unfollowed')<p class="mt-4 rounded-sm border border-state-good/30 bg-state-goodbg px-3 py-2 text-sm text-state-good" role="status">Unfollowed.</p>@endif
    <p class="mt-3 meta">@if($lastAlert)Last alert sent {{ $lastAlert->sent_on->format('j M Y') }} ({{ $lastAlert->changes_count }} {{ \Illuminate\Support\Str::plural('change', $lastAlert->changes_count) }}).@else No alert sent yet.@endif</p>
    @if($follows->isEmpty())
    <div class="mt-6"><x-site.empty title="You are not following anything yet">Open any policy, jurisdiction or obligation and use "Follow for daily alerts" in its sidebar.</x-site.empty>
    <div class="mt-4 flex flex-wrap gap-2"><a href="{{ route('policies.index') }}" class="btn-primary">Browse policies</a><a href="{{ route('jurisdictions.index') }}" class="btn-secondary">Browse jurisdictions</a></div></div>
    @else
    <ul class="mt-6 divide-y divide-brand-line border-y border-brand-line" aria-label="Followed records">
        @foreach($follows as $row)
        <li class="flex flex-wrap items-start justify-between gap-3 py-3">
            <div>@if($row['url'])<a href="{{ $row['url'] }}" class="font-medium text-brand-navy hover:underline">{{ $row['title'] }}</a>@else<span class="font-medium text-brand-navy">{{ $row['title'] }}</span> <span class="meta">(no longer published)</span>@endif
                <div class="text-xs text-brand-muted">{{ \App\Models\Follow::typeLabel($row['follow']->subject_type) }} · following since {{ $row['follow']->created_at->format('j M Y') }}</div></div>
            <form method="post" action="{{ route('follow.toggle', [$row['follow']->subject_type, $row['follow']->subject_slug]) }}">@csrf<input type="hidden" name="return" value="{{ route('following.index', absolute: false) }}"><button type="submit" class="btn-secondary !min-h-[36px] !py-1">Unfollow</button></form>
        </li>
        @endforeach
    </ul>
    @endif
    <p class="mt-6 text-sm"><a href="{{ route('profile.edit') }}">Back to your account</a></p>
</div>
@endsection
