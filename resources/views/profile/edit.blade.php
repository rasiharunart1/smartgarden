<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile Settings - Smart Farming</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #064e3b 50%, #0d9488 100%);
            min-height: 100vh;
        }

        .glass {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }

        .input-glass {
            background: rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: white;
            transition: all 0.3s ease;
        }

        .input-glass:focus {
            background: rgba(0, 0, 0, 0.2);
            border-color: rgba(16, 185, 129, 0.5); /* Emerald 500 */
            outline: none;
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }
        
        /* AutoFill Override */
        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active{
            -webkit-box-shadow: 0 0 0 30px #064e3b inset !important;
            -webkit-text-fill-color: white !important;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>
<body class="text-white p-4 md:p-8">

    <!-- Top Navigation -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="flex justify-between items-center bg-black/20 px-4 py-3 rounded-t-2xl glass border-b border-white/10">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 text-emerald-400 font-bold transition">
                <img src="{{ Auth::user()->avatar_url }}" class="w-8 h-8 rounded-full object-cover border-2 border-emerald-400/50 flex-shrink-0" alt="avatar">
                <span class="text-sm truncate max-w-[120px] sm:max-w-none hidden sm:inline">{{ Auth::user()->name }}</span>
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-xs sm:text-sm hover:text-red-400 text-white/70 transition flex items-center gap-1">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span class="hidden sm:inline">Logout</span>
                </button>
            </form>
        </div>
        <div class="flex items-center gap-1 bg-black/10 px-4 py-2 rounded-b-2xl glass">
            <a href="{{ route('dashboard') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-gauge mr-1"></i><span class="hidden sm:inline">Dashboard</span>
            </a>
            <a href="{{ route('devices.index') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-microchip mr-1"></i><span class="hidden sm:inline">Devices</span>
            </a>
            <a href="{{ route('logs.index') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i><span class="hidden sm:inline">Logs</span>
            </a>
        </div>
    </div>

    <!-- Header info -->
    <div class="max-w-4xl mx-auto mb-8 text-center mt-12">
        <div class="relative w-24 h-24 mx-auto mb-6 group">
            <img id="avatar-preview" src="{{ Auth::user()->avatar_url }}" alt="avatar"
                 class="w-24 h-24 rounded-3xl object-cover border-2 border-white/30 shadow-[0_0_30px_rgba(16,185,129,0.3)]">
            <label for="avatar-header-trigger" class="absolute inset-0 bg-black/50 rounded-3xl opacity-0 group-hover:opacity-100 transition flex items-center justify-center cursor-pointer">
                <i class="fa-solid fa-camera text-white text-xl"></i>
            </label>
        </div>
        <h1 class="text-4xl font-extrabold tracking-tight mb-2">Profile Settings</h1>
        <p class="text-white/60">Manage your account credentials, personal information, and platform access.</p>
    </div>

    <div class="max-w-4xl mx-auto space-y-8 pb-12">
        
        <!-- Update Profile Information -->
        <div class="glass rounded-3xl p-8 relative overflow-hidden shadow-2xl">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-xl font-bold mb-1 flex items-center"><i class="fa-solid fa-id-card mr-2 text-emerald-400"></i> {{ __('Profile Information') }}</h2>
                <p class="text-sm text-white/60 mb-8">{{ __("Update your account's profile information and email address.") }}</p>

                <form id="send-verification" method="post" action="{{ route('verification.send') }}">
                    @csrf
                </form>

                <form method="post" action="{{ route('profile.update') }}" class="space-y-6 max-w-xl w-full" enctype="multipart/form-data">
                    @csrf
                    @method('patch')

                    {{-- Avatar Upload --}}
                    <div>
                        <label class="block text-sm font-medium text-white/80 mb-3"><i class="fa-solid fa-camera mr-1 text-emerald-400"></i> Foto Profil</label>
                        <div class="flex items-center gap-5">
                            <img id="avatar-form-preview" src="{{ Auth::user()->avatar_url }}" alt="avatar"
                                 class="w-20 h-20 rounded-2xl object-cover border-2 border-white/20 flex-shrink-0">
                            <div class="space-y-2">
                                <label for="avatar" id="avatar-header-trigger" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl text-sm transition">
                                    <i class="fa-solid fa-arrow-up-from-bracket"></i> Ganti Foto
                                </label>
                                <input id="avatar" name="avatar" type="file" accept="image/*" class="hidden" onchange="previewAvatar(this)">
                                @if(Auth::user()->avatar)
                                <label class="flex items-center gap-2 text-xs text-red-400/80 cursor-pointer hover:text-red-400">
                                    <input type="checkbox" name="remove_avatar" value="1" class="rounded"> Hapus foto profil
                                </label>
                                @endif
                                <p class="text-xs text-white/40">JPG, PNG, WebP. Maks 2MB.</p>
                                @error('avatar')<span class="text-red-400 text-xs block">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    <!-- Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-white/80 mb-2">{{ __('Name') }}</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-user text-emerald-400/70"></i>
                            </div>
                            <input id="name" name="name" type="text" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" value="{{ old('name', $user->name) }}" required autofocus autocomplete="name" />
                        </div>
                        <x-input-error class="mt-2 text-red-400 text-sm" :messages="$errors->get('name')" />
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-white/80 mb-2">{{ __('Email Address') }}</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fa-solid fa-envelope text-emerald-400/70"></i>
                            </div>
                            <input id="email" name="email" type="email" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" value="{{ old('email', $user->email) }}" required autocomplete="username" />
                        </div>
                        <x-input-error class="mt-2 text-red-400 text-sm" :messages="$errors->get('email')" />

                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <div class="mt-4 p-4 bg-amber-500/10 border border-amber-500/30 rounded-xl text-sm">
                                <p class="text-amber-200">
                                    <i class="fa-solid fa-triangle-exclamation mr-1"></i>
                                    {{ __('Your email address is unverified.') }}

                                    <button form="send-verification" class="underline hover:text-white rounded-md focus:outline-none transition-colors ml-2 font-medium">
                                        {{ __('Click here to re-send the verification email.') }}
                                    </button>
                                </p>

                                @if (session('status') === 'verification-link-sent')
                                    <p class="mt-2 font-medium text-emerald-400">
                                        {{ __('A new verification link has been sent to your email address.') }}
                                    </p>
                                @endif
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 shadow-[0_4px_14px_0_rgba(16,185,129,0.39)] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-300 flex items-center">
                            {{ __('Save Changes') }} <i class="fa-solid fa-check ml-2"></i>
                        </button>

                        @if (session('status') === 'profile-updated')
                            <p
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 2000)"
                                class="text-sm text-emerald-400 flex items-center"
                            ><i class="fa-solid fa-circle-check mr-1"></i> {{ __('Saved.') }}</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Update Password -->
        <div class="glass rounded-3xl p-8 relative overflow-hidden shadow-2xl border-t border-white/5">
            <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-blue-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10">
                <h2 class="text-xl font-bold mb-1 flex items-center"><i class="fa-solid fa-lock mr-2 text-blue-400"></i> {{ __('Update Password') }}</h2>
                <p class="text-sm text-white/60 mb-8">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>

                <form method="post" action="{{ route('password.update') }}" class="space-y-6 max-w-xl w-full">
                    @csrf
                    @method('put')

                    <!-- Current Password -->
                    <div>
                        <label for="update_password_current_password" class="block text-sm font-medium text-white/80 mb-2">{{ __('Current Password') }}</label>
                        <div class="relative">
                            <input id="update_password_current_password" name="current_password" type="password" class="input-glass block w-full px-4 py-3 rounded-xl focus:ring-0 focus:border-blue-500 focus:shadow-[0_0_0_2px_rgba(59,130,246,0.2)]" autocomplete="current-password" placeholder="••••••••" />
                        </div>
                        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2 text-red-400 text-sm" />
                    </div>

                    <!-- New Password -->
                    <div>
                        <label for="update_password_password" class="block text-sm font-medium text-white/80 mb-2">{{ __('New Password') }}</label>
                        <div class="relative">
                            <input id="update_password_password" name="password" type="password" class="input-glass block w-full px-4 py-3 rounded-xl focus:ring-0 focus:border-blue-500 focus:shadow-[0_0_0_2px_rgba(59,130,246,0.2)]" autocomplete="new-password" placeholder="••••••••" />
                        </div>
                        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2 text-red-400 text-sm" />
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="update_password_password_confirmation" class="block text-sm font-medium text-white/80 mb-2">{{ __('Confirm Password') }}</label>
                        <div class="relative">
                            <input id="update_password_password_confirmation" name="password_confirmation" type="password" class="input-glass block w-full px-4 py-3 rounded-xl focus:ring-0 focus:border-blue-500 focus:shadow-[0_0_0_2px_rgba(59,130,246,0.2)]" autocomplete="new-password" placeholder="••••••••" />
                        </div>
                        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2 text-red-400 text-sm" />
                    </div>

                    <div class="flex items-center gap-4 pt-2">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 shadow-[0_4px_14px_0_rgba(59,130,246,0.39)] text-white font-bold py-2.5 px-6 rounded-xl transition-all duration-300 flex items-center">
                            {{ __('Update Password') }} <i class="fa-solid fa-key ml-2"></i>
                        </button>

                        @if (session('status') === 'password-updated')
                            <p
                                x-data="{ show: true }"
                                x-show="show"
                                x-transition
                                x-init="setTimeout(() => show = false, 2000)"
                                class="text-sm text-blue-400 flex items-center"
                            ><i class="fa-solid fa-circle-check mr-1"></i> {{ __('Updated.') }}</p>
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Account (Danger Zone) -->
        <div class="glass border-red-500/30 rounded-3xl p-8 relative overflow-hidden shadow-2xl bg-gradient-to-br from-black/0 via-black/0 to-red-900/10">
            <h2 class="text-xl font-bold text-red-400 mb-1 flex items-center"><i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ __('Danger Zone') }}</h2>
            <p class="text-sm text-white/60 mb-6">{{ __('Once your account is deleted, all of its resources and data, including IoT API telemetry logs, will be permanently deleted.') }}</p>

            <button x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')" class="bg-red-500/20 border border-red-500/50 hover:bg-red-500 hover:text-white text-red-300 font-bold py-2.5 px-6 rounded-xl transition-all duration-300 flex items-center">
                {{ __('Delete Account') }} <i class="fa-solid fa-trash ml-2"></i>
            </button>

            <!-- Using standard Blade X-Modal just overridden via Tailwind/Alpine -->
            <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
                <div class="bg-slate-900 border border-red-500/30 p-8 shadow-2xl">
                <form method="post" action="{{ route('profile.destroy') }}" class="p-2">
                    @csrf
                    @method('delete')

                    <h2 class="text-xl font-extrabold text-white flex items-center">
                        <i class="fa-solid fa-triangle-exclamation text-red-500 mr-2"></i>
                        {{ __('Are you sure you want to delete your account?') }}
                    </h2>

                    <p class="mt-3 text-sm text-gray-400">
                        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                    </p>

                    <div class="mt-6">
                        <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />

                        <input
                            id="password"
                            name="password"
                            type="password"
                            class="input-glass mt-1 block w-3/4 rounded-xl px-4 py-3"
                            placeholder="{{ __('Confirm Password') }}"
                        />

                        <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2 text-red-400" />
                    </div>

                    <div class="mt-8 flex justify-end gap-3">
                        <button x-on:click="$dispatch('close')" type="button" class="bg-white/10 hover:bg-white/20 text-white px-5 py-2.5 rounded-xl transition-colors font-semibold">
                            {{ __('Cancel') }}
                        </button>

                        <button type="submit" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2.5 rounded-xl transition-colors font-semibold shadow-lg shadow-red-600/30">
                            {{ __('Permanently Delete Account') }}
                        </button>
                    </div>
                </form>
                </div>
            </x-modal>
        </div>
        
    </div>

    @vite(['resources/js/app.js'])
    <script>
        function previewAvatar(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('avatar-preview').src = e.target.result;
                    document.getElementById('avatar-form-preview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
