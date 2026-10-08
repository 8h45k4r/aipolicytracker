@extends('backend.layouts.app', ['title' => 'Invite a user · Users'])
@section('content')
<p class="text-sm"><a href="{{ route('backend.admin.users.index') }}">← Users and roles</a></p>
<div class="mt-2">
    <x-backend.page-header title="Invite a user" description="A new account, with or without a role, and a link to choose its password." />
</div>
<div class="mt-5 max-w-2xl rounded-md border border-brand-line bg-white p-5">
    @include('backend.admin.users.invite-form', ['cancelUrl' => route('backend.admin.users.index')])
</div>
@endsection
