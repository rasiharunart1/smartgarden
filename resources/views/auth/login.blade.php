<x-guest-layout>
    <!-- Session Status -->
    <x-auth-session-status class="mb-4 text-emerald-400 text-sm" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Email Address') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="mail" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="email" class="input-glass block w-full pl-10 pr-3 py-3 rounded-xl focus:ring-0" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" placeholder="admin@email.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-2 text-red-400 text-sm" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Password') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="lock" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="password" class="input-glass block w-full pl-10 pr-3 py-3 rounded-xl focus:ring-0"
                            type="password"
                            name="password"
                            required autocomplete="current-password" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-2 text-red-400 text-sm" />
        </div>

        <!-- Remember Me & Forgot Password -->
        <div class="flex items-center justify-between text-sm">
            <label for="remember_me" class="inline-flex items-center cursor-pointer group">
                <input id="remember_me" type="checkbox" class="rounded border-white/20 bg-white/5 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-0 focus:ring-offset-transparent" name="remember">
                <span class="ms-2 text-white/60 group-hover:text-white/80 transition-colors">{{ __('Remember me') }}</span>
            </label>

            @if (Route::has('password.request'))
                <a class="text-emerald-400 hover:text-emerald-300 transition-colors" href="{{ route('password.request') }}">
                    {{ __('Forgot password?') }}
                </a>
            @endif
        </div>

        <div class="pt-2">
            <button class="w-full bg-emerald-500 hover:bg-emerald-600 shadow-[0_4px_14px_0_rgba(16,185,129,0.39)] hover:shadow-[0_6px_20px_rgba(16,185,129,0.23)] text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 flex justify-center items-center">
                {{ __('Log in') }} <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
            </button>
        </div>
        
        <div class="text-center mt-6 text-sm text-white/60">
            Don't have an account? 
            <a href="{{ route('register') }}" class="text-white font-semibold hover:text-emerald-400 transition-colors underline decoration-emerald-500/30 underline-offset-4">Sign up here</a>
        </div>
    </form>
</x-guest-layout>
