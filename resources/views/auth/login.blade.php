<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pasig Libreng Sakay - Login</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pasig_logo.png') }}">



    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @if ($showCaptcha)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif

    <style>
        /* Hide Microsoft Edge / IE default reveal/clear password icons to keep only the custom toggle */
        input::-ms-reveal,
        input::-ms-clear {
            display: none !important;
        }
    </style>


</head>

<body class="h-full bg-slate-50 text-slate-955 antialiased overflow-hidden">
    <div class="flex min-h-full">
        <!-- LEFT PANEL: Smart Mobility Hero Dashboard with bg.jpg and Blurred Blue Color Fade overlay -->
        <aside
            class="relative hidden w-[45%] shrink-0 overflow-hidden p-12 lg:flex lg:flex-col lg:justify-between border-r border-white/10 bg-cover bg-center"
            style="background-image: url('{{ asset('images/bg.jpg') }}');">

            <!-- Blurred Deep Blue Gradient Overlay Cover -->
            <div
                class="absolute inset-0 bg-gradient-to-br from-[#10234a]/95 via-[#0a0f1d]/88 to-[#1e3a8a]/80 backdrop-blur-[4px] z-0">
            </div>

            <!-- Header Section -->
            <div class="relative z-10 flex items-center gap-4 animate-fade-in-up">
                <div
                    class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl border border-white/15 bg-white/10 p-2.5 backdrop-blur-md shadow-sm">
                    <img src="{{ asset('images/pasig_logo_1.png') }}" alt="Pasig Seal"
                        class="h-full w-full object-contain">
                </div>
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-[0.25em] text-white/45">Pasig City</span>
                    <h2 class="text-lg font-bold tracking-tight text-white mt-0.5">Libreng Sakay</h2>
                </div>
            </div>

            <!-- Static Hero Typography & Icon Grid -->
            <div class="relative z-10 my-auto w-full max-w-lg py-16 animate-fade-in-up" style="animation-delay: 0.1s;">
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-widest text-white/75 backdrop-blur-md">
                    <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                    Fleet Management System
                </div>

                <h1 class="mt-8 text-5xl font-extrabold leading-[1.1] tracking-normal text-white">
                    The smarter way to manage your fleet.
                </h1>

                <p class="mt-6 text-base leading-8 text-white/60">
                    Monitor routes, track vehicles, and manage operations for Pasig City's free public transport
                    program.
                </p>

                <!-- Modern Features Grid Layout -->
                <div
                    class="mt-10 grid grid-cols-3 overflow-hidden rounded-2xl border border-white/12 bg-white/[0.05] backdrop-blur-md">
                    <div
                        class="flex flex-col items-center justify-center gap-2.5 px-3 py-5 text-center transition hover:bg-white/[0.03]">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white shadow-sm">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 21s7-4.35 7-11a7 7 0 10-14 0c0 6.65 7 11 7 11z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M12 10.5h.01" />
                            </svg>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-white/80">GPS Tracking</p>
                    </div>
                    <div
                        class="flex flex-col items-center justify-center gap-2.5 border-x border-white/12 px-3 py-5 text-center transition hover:bg-white/[0.03]">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white shadow-sm">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M8 6h12M8 12h12M8 18h12" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M4 6h.01M4 12h.01M4 18h.01" />
                            </svg>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-white/80">Trip Logs</p>
                    </div>
                    <div
                        class="flex flex-col items-center justify-center gap-2.5 px-3 py-5 text-center transition hover:bg-white/[0.03]">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white/10 text-white shadow-sm">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M15 17h5l-1.4-1.4A2 2 0 0118 14.17V11a6 6 0 10-12 0v3.17a2 2 0 01-.6 1.43L4 17h5" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                    d="M10 20a2 2 0 004 0" />
                            </svg>
                        </div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-white/80">Alerts</p>
                    </div>
                </div>
            </div>

            <!-- Footer Meta -->
            <div class="relative z-10 flex items-center justify-between text-xs font-semibold text-white/40 animate-fade-in-up"
                style="animation-delay: 0.2s;">
                <span>Pasig Transport Operations</span>
                <span>2026</span>
            </div>
        </aside>

        <!-- RIGHT PANEL: Elegant Modern Login (55% width, scales to 100% on mobile/tablet, White Background) -->
        <main class="flex flex-1 flex-col justify-center bg-white px-4 py-8 sm:px-12 md:px-16 lg:w-[55%] lg:px-20">
            <div class="mx-auto w-full max-w-[480px]">

                <!-- Premium Form Card Container (frosted shadow depth) -->
                <div
                    class="relative overflow-hidden rounded-[32px] border border-slate-100 bg-gradient-to-b from-slate-50/90 to-white/40 p-8 sm:p-10 shadow-[0_24px_60px_-15px_rgba(16,35,74,0.07)] backdrop-blur-md animate-fade-in-up">
                    <!-- Ambient Glow inside the card -->
                    <div
                        class="pointer-events-none absolute -right-10 -top-10 h-32 w-32 rounded-full bg-[#10234a]/3 blur-[40px]">
                    </div>

                    <!-- Unified Premium Brand Header with Logo & Seal (Inside the card for perfect alignment) -->
                    <div class="mb-6 flex flex-col items-center text-center relative z-10">
                        <div
                            class="flex h-20 w-auto shrink-0 items-center justify-center transition-transform duration-300 hover:scale-[1.03] -translate-x-[18px]">
                            <img src="{{ asset('images/logo-with-seal.png') }}" alt="Pasig City Seal"
                                class="h-full w-auto object-contain">
                        </div>
                        <div class="mt-3">
                            <span
                                class="text-[9px] font-extrabold uppercase tracking-[0.22em] text-[#10234a] block">Pasig
                                City Libreng Sakay</span>
                            <p class="text-[10px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Fleet
                                Management Portal</p>
                        </div>
                    </div>

                    <hr class="border-slate-200/50 mb-6 relative z-10">

                    <!-- Form Greeting Header -->
                    <div class="mb-6 relative z-10 text-center">
                        <h2 class="text-2xl font-extrabold tracking-tight text-slate-950 sm:text-3xl">Welcome back</h2>
                        <p class="mt-1.5 text-slate-500 text-sm font-medium">Sign in to your fleet management account.
                        </p>
                    </div>

                    <!-- Form Element -->
                    <form action="{{ route('login') }}" method="POST" autocomplete="off" class="space-y-5 relative z-10"
                        onsubmit="triggerLoadingState()">
                        @csrf

                        {{-- Error Alert --}}
                        @if ($errors->any())
                            <div class="flex items-start gap-3 rounded-xl border border-rose-300 bg-rose-100 px-4 py-3.5">
                                <i class="ti ti-shield-exclamation text-rose-600 text-xl shrink-0 mt-0.5"></i>
                                <div class="space-y-0.5">
                                    <p class="font-extrabold text-sm text-rose-900">Authentication failed</p>
                                    <p class="text-sm text-rose-700">Invalid credentials. Please check your email and password.</p>
                                </div>
                            </div>
                        @endif

                        <!-- Email Input with Floating Label (UI/UX Pro Max: 58px touch height, 16px font readability) -->
                        <div class="space-y-1">
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 transition-colors peer-focus:text-[#10234a] flex items-center justify-center">
                                    <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                </span>

                                <input id="email" type="email" name="email" value="{{ old('email') }}" placeholder=" "
                                    autocomplete="off" autofocus required
                                    class="peer block w-full h-[58px] rounded-2xl border border-slate-200 bg-slate-50 pl-11 pr-4 pt-5 pb-1.5 text-sm font-medium text-slate-955 outline-none transition-all placeholder-transparent hover:border-slate-300 focus:border-[#10234a] focus:bg-white focus:ring-4 focus:ring-[#10234a]/10">

                                <label for="email"
                                    class="pointer-events-none absolute left-11 top-4 origin-[0] -translate-y-3.5 scale-75 transform text-xs font-bold text-slate-400 transition-all duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-3.5 peer-focus:scale-75 peer-focus:text-[#10234a]">
                                    Email Address
                                </label>
                            </div>
                        </div>

                        <!-- Password Input with Floating Label & Visibility Toggle -->
                        <div class="space-y-1">
                            <div class="relative">
                                <span
                                    class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400 flex items-center justify-center">
                                    <svg class="h-4.5 w-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                    </svg>
                                </span>

                                <input id="password" type="password" name="password" placeholder=" "
                                    autocomplete="new-password" required
                                    class="peer block w-full h-[58px] rounded-2xl border border-slate-200 bg-slate-50 pl-11 pr-12 pt-5 pb-1.5 text-sm font-medium text-slate-955 outline-none transition-all placeholder-transparent hover:border-slate-300 focus:border-[#10234a] focus:bg-white focus:ring-4 focus:ring-[#10234a]/10">

                                <label for="password"
                                    class="pointer-events-none absolute left-11 top-4 origin-[0] -translate-y-3.5 scale-75 transform text-xs font-bold text-slate-400 transition-all duration-200 peer-placeholder-shown:translate-y-0 peer-placeholder-shown:scale-100 peer-focus:-translate-y-3.5 peer-focus:scale-75 peer-focus:text-[#10234a]">
                                    Password
                                </label>

                                <!-- Custom Toggle Button (Touch target size > 44px) -->
                                <button type="button" onclick="togglePassword()"
                                    class="absolute right-2 top-1/2 z-10 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-100 hover:text-[#10234a] cursor-pointer">
                                    <svg id="eye-icon" class="h-5 w-5" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                                            d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                    </svg>
                                </button>


                            </div>
                        </div>

                        <!-- Aux Options (Remember & Forgot Link) -->
                        <div class="flex items-center justify-between pt-1">
                            <label class="flex items-center gap-2.5 cursor-pointer group">
                                <input type="checkbox" name="remember"
                                    class="h-4 w-4 rounded border-slate-200 bg-slate-50 text-[#10234a] focus:ring-[#10234a]/20 cursor-pointer">
                                <span
                                    class="text-xs font-semibold text-slate-500 transition group-hover:text-slate-700 select-none">Remember
                                    device</span>
                            </label>
                            <a href="#" class="text-xs font-bold text-[#10234a] transition hover:text-[#0b1936]">
                                Forgot password?
                            </a>
                        </div>

                        @if ($showCaptcha)
                            <!-- Styled Turnstile Block -->
                            <div class="flex justify-center py-2.5">
                                <div class="cf-turnstile scale-95 origin-center shadow-md rounded-xl overflow-hidden"
                                    data-sitekey="{{ config('services.turnstile.site_key') }}" data-theme="light"></div>
                            </div>
                            @error('cf-turnstile-response')
                                <p class="text-xs font-bold text-rose-600 text-center -mt-1.5">{{ $message }}</p>
                            @enderror
                        @endif

                        <!-- Submit Button with brand-aligned deep blue styling -->
                        <button type="submit" id="submit-btn"
                            class="relative flex h-[58px] w-full items-center justify-center gap-2 overflow-hidden rounded-2xl bg-[#10234a] px-4 text-sm font-extrabold text-white shadow-[0_18px_36px_-18px_rgba(16,35,74,0.75)] transition-all duration-300 hover:-translate-y-0.5 hover:bg-[#0b1936] active:translate-y-0 focus:outline-none focus:ring-4 focus:ring-[#10234a]/20 cursor-pointer">
                            <span id="btn-text" class="flex items-center gap-2">
                                Sign In
                                <svg class="h-4.5 w-4.5 transition-transform duration-300 group-hover:translate-x-1"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2"
                                        d="M14 5l7 7m0 0l-7 7m7-7H3" />
                                </svg>
                            </span>

                            <!-- Loading spinner -->
                            <span id="btn-spinner" class="hidden items-center gap-2">
                                <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                        stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                    </path>
                                </svg>
                                Connecting securely...
                            </span>
                        </button>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <!-- Password Visibility Toggle & Personalizer Greeting Scripts -->
    <script>
        // Password visibility toggler
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('eye-icon');

            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                `;
            } else {
                input.type = 'password';
                icon.innerHTML = `
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                `;
            }
        }
        // Button Loading State Trigger
        function triggerLoadingState() {
            const submitBtn = document.getElementById('submit-btn');
            const btnText = document.getElementById('btn-text');
            const btnSpinner = document.getElementById('btn-spinner');

            btnText.classList.add('hidden');
            btnSpinner.classList.remove('hidden');
            btnSpinner.classList.add('flex');

            // Add loading background scale
            submitBtn.classList.remove('bg-[#10234a]', 'hover:bg-[#0b1936]');
            submitBtn.classList.add('bg-[#0b1936]', 'pointer-events-none');
        }
    </script>
</body>

</html>