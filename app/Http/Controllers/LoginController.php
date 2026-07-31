<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Log;
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

        return response()->view('auth.login', compact('showCaptcha'))->withHeaders($this->noStoreHeaders());
    }

    public function authenticate(LoginRequest $request)
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        // Redirect based on user role
        switch ($user->role) {
            case 'admin':
                return $this->redirectAfterLogin(route('admin.dashboard'));
            case 'fleet_manager':
                return $this->redirectAfterLogin(route('fleet.dashboard'));
            case 'driver':
                return $this->redirectAfterLogin(route('driver.dashboard'));
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

    public function autoLoginFleetManager()
    {
        // ISSUE-051 FIX: Guard auto-login backdoor from production environments.
        if (app()->environment('production')) {
            abort(404);
        }

        $user = User::where('role', 'fleet_manager')->first();
        if ($user) {
            Auth::login($user);
            request()->session()->regenerate();
            return $this->redirectAfterLogin(route('fleet.dashboard'));
        }
        return 'Fleet Operations Manager user not found';
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login')->withHeaders($this->noStoreHeaders());
    }
    private function noStoreHeaders(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => 'Fri, 01 Jan 1990 00:00:00 GMT',
        ];
    }
    private function redirectAfterLogin(string $defaultUrl)
    {
        $intended = session()->pull('url.intended');

        if ($intended && ! $this->isApiUrl($intended)) {
            return redirect()->to($intended);
        }

        return redirect()->to($defaultUrl);
    }

    private function isApiUrl(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $path = '/' . ltrim($path, '/');

        return str_starts_with($path, '/api/')
            || str_contains($path, '/api/');
    }
}

