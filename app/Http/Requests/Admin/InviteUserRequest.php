<?php

namespace App\Http\Requests\Admin;

use App\Enums\AdminRole;
use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Backend → Users and roles → Invite a user. Who may send it is the route's business
 * (can:users.manage); this is what may be sent. A role is one of the storable roles or
 * none; ownership is never on offer, and an owner address is refused because an owner's
 * account is created by its owner registering with it.
 */
class InviteUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255', Rule::unique('users', 'email'), new NotDisposableEmail],
            'admin_role' => ['nullable', 'string', Rule::in(AdminRole::values())],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['admin_role' => 'role'];
    }

    /** @return list<callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('email')) {
                return;
            }
            $owners = array_map('strtolower', config('aipolicytracker.admin_emails', []));
            if (in_array(Str::lower((string) $this->input('email')), $owners, true)) {
                $validator->errors()->add('email', 'This address is an owner (ADMIN_EMAILS). Owners create their own account by registering.');
            }
        }];
    }

    public function role(): ?AdminRole
    {
        return AdminRole::tryFrom((string) $this->validated('admin_role'));
    }
}
