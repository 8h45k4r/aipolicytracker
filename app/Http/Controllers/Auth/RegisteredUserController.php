<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
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
            'email' => 'required|string|lowercase|email|max:255|unique:' . User::class,
            'phone_no' => 'required|numeric',
            'organization_name' => 'nullable|string|max:255',
            'organization_email' => 'nullable|string|lowercase|email|max:255',
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
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->userInfo()->create([
            'phone_no' => $validate['phone_no'],
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

        return to_route('verification.notice');
    }
}
