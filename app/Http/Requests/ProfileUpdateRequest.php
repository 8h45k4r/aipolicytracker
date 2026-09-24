<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            // An admin's display name is what the reviewer roster matches and what a
            // verification is signed with, so an admin cannot rename themselves into
            // someone else's roster entry. Ordinary accounts rename freely.
            'name' => ['required', 'string', 'max:255', function (string $attribute, mixed $value, \Closure $fail) {
                $user = $this->user();
                if ($user->isAdmin() && $value !== $user->name) {
                    $fail('Admin display names are tied to the reviewer roster and cannot be changed here.');
                }
            }],
            // The address policy applies to a change, not to the address already on
            // the account: an existing reader is never locked out by a rule added later.
            'email' => array_filter([
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
                strtolower((string) $this->input('email')) === strtolower((string) $this->user()->email) ? null : new NotDisposableEmail,
            ]),
        ];
    }
}
