<x-guest-layout>
    <form method="POST" action="{{ route('register') }}" class="space-y-4">
        @csrf

        <!-- Name -->
        <div>
            <label for="name" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Full Name') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="user" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="name" class="input-glass block w-full pl-10 pr-3 py-2.5 rounded-xl focus:ring-0" type="text" name="name" value="{{ old('name') }}" required autofocus autocomplete="name" placeholder="John Doe" />
            </div>
            <x-input-error :messages="$errors->get('name')" class="mt-1 text-red-400 text-xs" />
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Email Address') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="mail" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="email" class="input-glass block w-full pl-10 pr-3 py-2.5 rounded-xl focus:ring-0" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="john@example.com" />
            </div>
            <x-input-error :messages="$errors->get('email')" class="mt-1 text-red-400 text-xs" />
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Password') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="lock" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="password" class="input-glass block w-full pl-10 pr-3 py-2.5 rounded-xl focus:ring-0"
                            type="password"
                            name="password"
                            required autocomplete="new-password" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password')" class="mt-1 text-red-400 text-xs" />
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-white/80 mb-1 tracking-wide">{{ __('Confirm Password') }}</label>
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <i data-lucide="check-circle" class="w-5 h-5 text-white/40"></i>
                </div>
                <input id="password_confirmation" class="input-glass block w-full pl-10 pr-3 py-2.5 rounded-xl focus:ring-0"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" placeholder="••••••••" />
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-red-400 text-xs" />
        </div>

        <div class="pt-4">
            <button class="w-full bg-emerald-500 hover:bg-emerald-600 shadow-[0_4px_14px_0_rgba(16,185,129,0.39)] hover:shadow-[0_6px_20px_rgba(16,185,129,0.23)] text-white font-bold py-3 px-4 rounded-xl transition-all duration-300 flex justify-center items-center">
                {{ __('Create Account') }} <i data-lucide="user-plus" class="w-4 h-4 ml-2"></i>
            </button>
        </div>
        
        <div class="text-center mt-4 text-sm text-white/60">
            Already registered? 
            <a href="{{ route('login') }}" class="text-white font-semibold hover:text-emerald-400 transition-colors underline decoration-emerald-500/30 underline-offset-4">Log in here</a>
        </div>
    </form>
</x-guest-layout>
