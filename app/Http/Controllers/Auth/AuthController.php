<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Inertia\Inertia;
use App\Models\User;

class AuthController extends Controller
{
    public function viewLogin()
    {
        return inertia('Auth/Login');
    }

    
    public function login(Request $request)
    {
        // 1. Validate login credentials
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        // 2. Get Remember Me preference
        $remember = $request->boolean('remember');

        // 3. Generate rate limiting key
        $key = Str::lower($validated['username'])
            . '|' . $request->ip();

        // 4. Check failed login attempts
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'username' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // 5. Prepare authentication credentials
        $credentials = [
            'username' => $validated['username'],
            'password' => $validated['password'],
            'status' => 'active',
        ];

        // 6. Attempt authentication
        if (!Auth::attempt($credentials, $remember)) {

            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([
                'username' => 'Invalid username or password.',
            ]);
        }

        // 7. Clear failed attempts
        RateLimiter::clear($key);

        // 8. Regenerate session
        $request->session()->regenerate();

        // 9. Update last login date
        $request->user()->forceFill([
            'last_login_at' => now(),
        ])->save();

        // 10. Redirect to dashboard
        return redirect()->intended(route('dashboard'));
    }


    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
