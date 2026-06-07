<?php

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $attempts = RateLimiter::attempts($this->ipThrottleKey());
        $requiresCaptcha = $attempts >= 3;

        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string'],
            'cf-turnstile-response' => ($requiresCaptcha && ! app()->environment('testing')) ? ['required', 'string'] : ['nullable'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();
        $this->validateTurnstile();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey(), 900); // Track lockout failures for 15 minutes (900 seconds)
            RateLimiter::hit($this->ipThrottleKey(), 900); // Track IP failures for Turnstile for 15 minutes (900 seconds)

            $failedMessage = __('auth.failed');
            if ($failedMessage === 'auth.failed') {
                $failedMessage = 'The provided credentials do not match our records.';
            }

            throw ValidationException::withMessages([
                'email' => $failedMessage,
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        RateLimiter::clear($this->ipThrottleKey());
    }

    /**
     * Validate Cloudflare Turnstile token.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function validateTurnstile(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $attempts = RateLimiter::attempts($this->ipThrottleKey());
        if ($attempts < 3) {
            return; // Bypass Turnstile checks if IP failures are less than 3
        }

        $token = $this->input('cf-turnstile-response');

        if (! $token) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Please complete the CAPTCHA validation.',
            ]);
        }

        $response = \Illuminate\Support\Facades\Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => config('services.turnstile.secret_key'),
            'response' => $token,
            'remoteip' => $this->ip(),
        ]);

        if (! $response->successful() || ! $response->json('success')) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'CAPTCHA validation failed. Please try again.',
            ]);
        }
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        $throttleMessage = trans('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => ceil($seconds / 60),
        ]);

        if ($throttleMessage === 'auth.throttle') {
            $minutes = ceil($seconds / 60);
            $throttleMessage = "Too many login attempts. Please try again in {$minutes} " . ($minutes === 1 ? 'minute' : 'minutes') . ".";
        }

        throw ValidationException::withMessages([
            'email' => $throttleMessage,
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->input('email')).'|'.$this->ip());
    }

    /**
     * Get the IP-based rate limiting key for conditional CAPTCHA checks.
     */
    public function ipThrottleKey(): string
    {
        return 'login_attempts_ip|' . $this->ip();
    }
}
