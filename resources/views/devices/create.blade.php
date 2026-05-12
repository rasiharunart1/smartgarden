<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Device - Smart Farming</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

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
    </style>
</head>
<body class="text-white p-4 md:p-8">

    <!-- Top Navigation / Controls -->
    <div class="max-w-7xl mx-auto mb-6 flex justify-between items-center bg-black/20 p-4 rounded-2xl glass">
        <div class="flex items-center space-x-4">
            <a href="{{ route('profile.edit') }}" class="flex items-center space-x-2 hover:text-emerald-400 transition">
                <i data-lucide="user" class="w-5 h-5"></i>
                <span class="font-medium hidden sm:inline">{{ Auth::user()->name }}</span>
            </a>
        </div>

        <div class="flex items-center space-x-3 overflow-x-auto">
            <a href="{{ route('dashboard') }}" class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-1"></i> Dashboard
            </a>
            <a href="{{ route('devices.index') }}" class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="hard-drive" class="w-4 h-4 mr-1"></i> Devices
            </a>
            <a href="{{ route('logs.index') }}" class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="history" class="w-4 h-4 mr-1"></i> Logs
            </a>
        </div>
    </div>

    <div class="max-w-3xl mx-auto space-y-6 mt-12">
        <header class="text-center mb-8">
            <div class="w-20 h-20 mx-auto bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg border border-white/30 mb-4 cursor-default transition-all hover:scale-105">
                <i data-lucide="plus-circle" class="w-10 h-10 text-white drop-shadow-md"></i>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Register New API Device</h1>
            <p class="text-white/60 text-sm mt-2">Add a new board (e.g. ESP32/Raspberry Pi) and generate its secure API Key.</p>
        </header>

        <div class="glass rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl"></div>    

            <form method="POST" action="{{ route('devices.store') }}" class="space-y-6 relative z-10">
                @csrf

                <!-- Device Name -->
                <div>
                    <label for="device_name" class="block font-medium text-sm text-white/80 mb-2">Device Name <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="cpu" class="w-5 h-5 text-emerald-400/70"></i>
                        </div>
                        <input id="device_name" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" type="text" name="device_name" value="{{ old('device_name') }}" required autofocus placeholder="e.g. GreenHouse Node #1" />
                    </div>
                    @error('device_name')
                        <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Location -->
                <div>
                    <label for="location" class="block font-medium text-sm text-white/80 mb-2">Location (Optional)</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                            <i data-lucide="map-pin" class="w-5 h-5 text-emerald-400/70"></i>
                        </div>
                        <input id="location" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Block A / Garden West" />
                    </div>
                    @error('location')
                        <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Status Checkbox -->
                <div class="bg-black/10 rounded-xl p-4 border border-white/5 flex items-center">
                    <label for="status" class="inline-flex items-center cursor-pointer group">
                        <input id="status" type="checkbox" value="1" class="w-5 h-5 rounded border-white/20 bg-white/5 text-emerald-500 focus:ring-emerald-500 focus:ring-offset-0 focus:ring-offset-transparent outline-none" name="status" {{ old('status', true) ? 'checked' : '' }}>
                        <span class="ml-3 text-sm text-white/80 font-medium group-hover:text-white transition-colors">
                            Initialize as Active
                            <p class="text-xs text-white/40 font-normal mt-0.5">Allow device to start pushing telemetry data immediately upon creation.</p>
                        </span>
                    </label>
                </div>
                @error('status')
                    <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>
                @enderror

                <div class="flex items-center justify-between pt-4 mt-8 border-t border-white/10">
                    <a href="{{ route('devices.index') }}" class="text-white/60 hover:text-white transition-colors text-sm px-4 py-2 flex items-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Cancel
                    </a>

                    <button type="submit" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 shadow-[0_0_20px_rgba(16,185,129,0.3)] hover:shadow-[0_0_30px_rgba(16,185,129,0.5)] text-white font-bold py-3 px-8 rounded-xl transition-all duration-300 flex items-center justify-center transform hover:-translate-y-0.5">
                        <i data-lucide="save" class="w-5 h-5 mr-2"></i> Create API Device
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
