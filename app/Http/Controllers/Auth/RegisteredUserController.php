<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): \Illuminate\View\View
    {
        // Server-rendered in the site theme; the legacy Inertia page required phone numbers.
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {

        $validate = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class, new NotDisposableEmail],
            'phone_no' => 'nullable|numeric',
            'marketing_consent' => 'nullable|boolean',
            // Required when the reader arrived to download a template: a template download
            // is a lead, and a lead without an organisation cannot be followed up.
            'organization_name' => [\Illuminate\Validation\Rule::requiredIf(fn () => str_contains((string) session('url.intended'), '/guides/tools/')), 'nullable', 'string', 'max:255'],
            'organization_email' => ['nullable', 'string', 'lowercase', 'email', 'max:255', new NotDisposableEmail],
            'password' => [
                'required',
                'string',
                'min:8', // Minimum length of 8
                'regex:/[A-Z]/', // At least one uppercase letter
                'regex:/[a-z]/', // At least one lowercase letter
                'regex:/[!@#$%^&*(),.?":{}|<>]/', // At least one symbol
                'confirmed',
                Rules\Password::defaults(),
            ],
            'terms_condition' => 'accepted',
        ], [
            'password.min' => 'The password must be at least 8 characters.',
            'password.regex' => 'The password must contain at least one uppercase letter, one lowercase letter, and one special character.',
            'terms_condition.accepted' => 'You must accept the terms and conditions.',
            'organization_name.required' => 'Please tell us the organisation the template is for.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);
        // Terms acceptance and marketing consent are stored separately; consent is never pre-ticked.
        $user->forceFill([
            'terms_accepted_at' => now(),
            'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
            'organization_name' => $request->organization_name,
            'signup_source' => str_contains((string) session('url.intended'), '/guides/tools/') ? 'free-tool' : 'site',
        ])->save();

        $user->userInfo()->create([
            'phone_no' => $validate['phone_no'] ?? null,
            'organization_name' => $request->organization_name,
            'organization_email' => $request->organization_email,
            'ip_address' => $request->ip(),
            'user_agent' => $request->header('User-Agent'),
            'last_activity' => now()->timestamp,
            'terms_condition' => true,
            'status' => true,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->intended(route('home'))->with('success', 'Your free account is ready. We sent a verification link to '.$user->email.'.');
    }
}
