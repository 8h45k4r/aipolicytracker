@extends('backend.layouts.app', ['title' => 'Role permissions'])
@section('content')
<p class="text-sm"><a href="{{ route('backend.admin.users.index') }}">← Users and roles</a></p>
<h1 class="mt-2 font-display text-2xl font-semibold text-brand-navy">Role permissions</h1>
<p class="mt-1 meta max-w-3xl">What each role may do. A change applies to everyone holding the role from their next page load, and is recorded in the audit log. Saving asks you to confirm your password.</p>

<div class="mt-4 rounded-sm border border-brand-line bg-white p-4 text-sm">
    <p class="font-semibold text-brand-navy">Owner-only permissions cannot be given to a role</p>
    <p class="mt-1 text-brand-body">Platform settings, billing and user management stay with the owners in <code class="font-mono text-xs">ADMIN_EMAILS</code>. A role that could manage users could make itself anything, so those rows are locked here and refused by the server whatever is submitted.</p>
</div>

@php($groups = collect($capabilities)->groupBy(fn ($c) => $c->group()))
<form method="post" action="{{ route('backend.admin.users.permissions.update') }}" class="mt-6">@csrf
    @foreach($roles as $role)<input type="hidden" name="roles[]" value="{{ $role->value }}">@endforeach
    <div class="table-wrap bg-white">
        <table>
            <caption class="sr-only">Permissions by role. Tick a box to give the role that permission.</caption>
            <thead>
                <tr>
                    <th scope="col">Permission</th>
                    <th scope="col" class="text-center">Owner</th>
                    @foreach($roles as $role)
                        <th scope="col" class="text-center">
                            <span class="block">{{ $role->label() }}</span>
                            <span class="block text-[11px] font-normal normal-case text-brand-muted">{{ $holders[$role->value] ?? 0 }} {{ \Illuminate\Support\Str::plural('holder', $holders[$role->value] ?? 0) }} · {{ $edited[$role->value] ? 'edited' : 'defaults' }}</span>
                        </th>
                    @endforeach
                </tr>
            </thead>
            @foreach(['Editorial work', 'Reading', 'Owner only'] as $group)
                @continue(! $groups->has($group))
                <tbody>
                    <tr><th scope="rowgroup" colspan="{{ 2 + count($roles) }}" class="bg-brand-paper text-xs uppercase tracking-wide text-brand-muted">{{ $group }}</th></tr>
                    @foreach($groups[$group] as $c)
                        <tr>
                            <th scope="row" class="!bg-white !text-sm !font-normal !normal-case !tracking-normal !text-brand-body">{{ $c->label() }}</th>
                            <td class="text-center"><span class="text-state-good" aria-label="Owner: always">✓</span></td>
                            @foreach($roles as $role)
                                @php($on = in_array($c, $current[$role->value], true))
                                @php($default = in_array($c, $role->defaultCapabilities(), true))
                                <td class="text-center">
                                    @if($c->ownerOnly())
                                        <span class="text-brand-muted" aria-label="{{ $role->label() }}: never (owner only)">–</span>
                                    @else
                                        <label class="inline-flex items-center gap-1">
                                            <input type="checkbox" name="capabilities[{{ $role->value }}][]" value="{{ $c->value }}" @checked($on) aria-label="{{ $role->label() }}: {{ $c->label() }}">
                                            @if($on !== $default)<span class="text-[10px] text-state-warn" title="Differs from the default">●</span><span class="sr-only">(changed from default)</span>@endif
                                        </label>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>
    <p class="mt-2 meta"><span class="text-state-warn">●</span> marks a permission that differs from the role's default.</p>
    <div class="mt-4 flex flex-wrap items-center gap-2">
        <button class="btn-primary" data-confirm="Save these permissions? They apply to every holder of each role.">Save permissions</button>
    </div>
</form>

<section class="mt-8" aria-labelledby="reset-heading">
    <h2 id="reset-heading" class="font-display text-lg font-semibold text-brand-navy">Reset a role</h2>
    <div class="mt-3 grid gap-3 md:grid-cols-3">
        @foreach($roles as $role)
            <div class="card-flat bg-white p-4 text-sm">
                <p class="font-semibold text-brand-navy">{{ $role->label() }}</p>
                <p class="mt-1 text-brand-body">@if($edited[$role->value])<span class="font-medium">Default:</span> @endif{{ $role->description() }}</p>
                @if($edited[$role->value])
                    <p class="mt-2 meta">Edited {{ $edited[$role->value]->updated_at?->format('j M Y') }}@if($edited[$role->value]->updatedBy) by {{ $edited[$role->value]->updatedBy->email }}@endif.</p>
                    <form method="post" action="{{ route('backend.admin.users.permissions.reset') }}" class="mt-2" data-confirm="Put {{ $role->label() }} back to its default permissions?">@csrf<input type="hidden" name="role" value="{{ $role->value }}"><button class="btn-secondary !min-h-0 !py-1 text-xs">Reset to defaults</button></form>
                @else
                    <p class="mt-2 meta">Using its defaults.</p>
                @endif
            </div>
        @endforeach
    </div>
</section>
@endsection
