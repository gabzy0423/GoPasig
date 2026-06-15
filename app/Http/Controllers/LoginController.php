<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Requests\LoginRequest;
use App\Models\User;

class LoginController extends Controller
{
    public function index()
    {
        $ip = request()->ip();
        $attempts = RateLimiter::attempts('login_attempts_ip|' . $ip);
        $threshold = (int) \App\Models\SystemSetting::get('captcha_attempt_threshold', 3);
        $showCaptcha = $attempts >= $threshold;

        return view('auth.login', compact('showCaptcha'));
    }

    public function authenticate(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Redirect based on user role
        switch ($user->role) {
            case 'admin':
                return redirect()->intended(route('admin.dashboard'));
            case 'dispatcher':
                return redirect()->intended(route('fleet.dashboard'));
            case 'driver':
                return redirect()->intended(route('driver.dashboard'));
            default:
                // If any other role logs in, log out and redirect back with an error
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return back()->withErrors([
                    'email' => 'Unauthorized access role.',
                ])->onlyInput('email');
        }
    }

    public function autoLoginDispatcher()
    {
        $user = User::where('role', 'dispatcher')->first();
        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            return redirect()->intended(route('fleet.dashboard'));
        }
        return 'Dispatcher user not found';
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
