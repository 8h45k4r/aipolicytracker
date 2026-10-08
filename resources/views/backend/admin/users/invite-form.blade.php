{{-- The invitation form, shared by the side panel on the list and the page of its own.
     Errors show beside their field only; the layout's toast carries the first of them, so
     there is no second list. $roleOptions comes from UserController::roleOptions(): each
     role with what it can do now, an owner's edit on the permissions page included. --}}
@php($fieldError = fn (string $field) => $errors->first($field))
<form method="post" action="{{ route('backend.admin.users.invite') }}" id="invite-form" class="space-y-4">
    @csrf
    {{-- Marks which form came back with errors, so the list reopens this panel and no other. --}}
    <input type="hidden" name="_form" value="invite">
    <p class="text-sm text-brand-body">Creates the account and emails a link to choose a password. The link works for {{ $inviteDays }} days; choosing the password also confirms the address. Owners are not invited: they register with an address in <code class="font-mono text-xs">ADMIN_EMAILS</code>.</p>

    @foreach(['name' => ['Name', 'text', 120], 'email' => ['Email', 'email', 255]] as $field => [$label, $type, $max])
        <div>
            <label for="invite-{{ $field }}" class="label">{{ $label }}</label>
            <input id="invite-{{ $field }}" type="{{ $type }}" name="{{ $field }}" value="{{ old($field) }}" required maxlength="{{ $max }}" class="input" autocomplete="off"
                @if($fieldError($field)) aria-invalid="true" aria-describedby="invite-{{ $field }}-error" @endif>
            @if($fieldError($field))<p id="invite-{{ $field }}-error" class="mt-1 text-xs text-state-bad">{{ $fieldError($field) }}</p>@endif
        </div>
    @endforeach

    <fieldset @if($fieldError('admin_role')) aria-describedby="invite-role-error" @endif>
        <legend class="label">Role</legend>
        <p class="-mt-0.5 mb-2 text-xs text-brand-muted">What they can do in the admin. A role with admin access is asked to enrol an authenticator at first sign-in.</p>
        <div class="grid gap-2">
            @foreach($roleOptions as $option)
                @php($radioId = 'invite-role-'.($option['value'] ?: 'none'))
                <label for="{{ $radioId }}" class="block cursor-pointer rounded-md border border-brand-line bg-white p-3 hover:border-brand-navy has-[:checked]:border-brand-navy has-[:checked]:bg-brand-paper has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-brand-cyan" data-role-option="{{ $option['value'] ?: 'none' }}">
                    <span class="flex items-start gap-2">
                        <input id="{{ $radioId }}" type="radio" name="admin_role" value="{{ $option['value'] }}" class="mt-0.5" @checked((string) old('admin_role', '') === $option['value']) aria-describedby="{{ $radioId }}-desc">
                        <span class="min-w-0">
                            <span class="font-semibold text-brand-navy">{{ $option['label'] }}</span>
                            @if($option['edited'])<span class="badge-neutral ml-1">Edited by an owner</span>@endif
                            <span id="{{ $radioId }}-desc" class="mt-0.5 block text-xs text-brand-body">
                                {{ $option['description'] }}
                                @if($option['capabilities'] !== [])
                                    <span class="sr-only">Can:</span>
                                    <span class="mt-1.5 flex flex-wrap gap-1" data-role-capabilities>
                                        @foreach($option['capabilities'] as $capability)<span class="rounded-sm bg-brand-paper px-1.5 py-0.5 text-[11px] text-brand-navy ring-1 ring-inset ring-brand-line">{{ $capability }}</span>@endforeach
                                    </span>
                                @elseif($option['value'] !== '')
                                    <span class="mt-1 block text-state-warn">This role currently holds no permissions.</span>
                                @endif
                            </span>
                        </span>
                    </span>
                </label>
            @endforeach
        </div>
        @if($fieldError('admin_role'))<p id="invite-role-error" class="mt-1 text-xs text-state-bad">{{ $fieldError('admin_role') }}</p>@endif
    </fieldset>

    <div>
        <label for="invite-note" class="label">Personal note <span class="font-normal text-brand-muted">(optional)</span></label>
        <textarea id="invite-note" name="note" maxlength="500" rows="3" class="input" placeholder="Shown in the email"
            @if($fieldError('note')) aria-invalid="true" aria-describedby="invite-note-error" @endif>{{ old('note') }}</textarea>
        @if($fieldError('note'))<p id="invite-note-error" class="mt-1 text-xs text-state-bad">{{ $fieldError('note') }}</p>@endif
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <button type="submit" class="btn-primary">Create account and send invitation</button>
        @isset($cancelUrl)<a href="{{ $cancelUrl }}" class="btn-secondary">Cancel</a>@endisset
    </div>
</form>
