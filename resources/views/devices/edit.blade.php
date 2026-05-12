<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Device - Smart Farming</title>
    
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
            border-color: rgba(59, 130, 246, 0.5); /* Blue 500 */
            outline: none;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2);
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
        <header class="text-center mb-8 flex flex-col items-center">
            <div class="flex space-x-4 items-center">
                <div class="w-16 h-16 bg-gradient-to-tr from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(59,130,246,0.5)] border border-white/30 mb-4 cursor-default">
                    <i data-lucide="edit-3" class="w-8 h-8 text-white drop-shadow-md"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Edit Settings</h1>
            <p class="text-white/50 text-sm mt-2">Updating settings for <span class="font-semibold text-white/90">{{ $device->device_name }}</span></p>
        </header>

        <div class="glass rounded-3xl overflow-hidden shadow-2xl relative">
            <div class="absolute -right-32 -top-32 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl"></div>    

            <!-- API Code Banner Alert -->
            <div class="bg-black/30 p-6 border-b border-white/10 relative z-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-blue-400 mb-1 flex items-center">
                        <i data-lucide="key" class="w-3 h-3 mr-1"></i> API Connection Code
                    </h3>
                    <p class="text-xs text-white/50">Inject this UUID header (`X-Device-Code`) in your HTTP POST requests.</p>
                </div>
                <!-- Copyable Code Block -->
                <div class="bg-black/50 border border-white/10 rounded-lg p-3 flex items-center w-full sm:w-auto overflow-hidden group cursor-pointer" onclick="navigator.clipboard.writeText('{{ $device->device_code }}'); alert('Code copied to clipboard: {{ $device->device_code }}');" title="Click to copy">
                    <code class="font-mono text-sm text-emerald-300 truncate select-all">{{ $device->device_code }}</code>
                    <i data-lucide="copy" class="w-4 h-4 ml-3 text-white/30 group-hover:text-white transition-colors flex-shrink-0"></i>
                </div>
            </div>

            <div class="p-8 relative z-10">
                <form method="POST" action="{{ route('devices.update', $device) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <!-- Device Name -->
                    <div>
                        <label for="device_name" class="block font-medium text-sm text-white/80 mb-2">Device Name <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i data-lucide="cpu" class="w-5 h-5 text-blue-400/70"></i>
                            </div>
                            <input id="device_name" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" type="text" name="device_name" value="{{ old('device_name', $device->device_name) }}" required autofocus />
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
                                <i data-lucide="map-pin" class="w-5 h-5 text-blue-400/70"></i>
                            </div>
                            <input id="location" class="input-glass block w-full pl-12 pr-4 py-3 rounded-xl focus:ring-0" type="text" name="location" value="{{ old('location', $device->location) }}" />
                        </div>
                        @error('location')
                            <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>
                        @enderror
                    </div>

                    <!-- Status Checkbox -->
                    <div class="bg-black/10 rounded-xl p-4 border border-white/5 flex items-center">
                        <label for="status" class="inline-flex items-center cursor-pointer group w-full">
                        <input id="status" type="checkbox" value="1" class="w-5 h-5 rounded border-white/20 bg-white/5 text-blue-500 focus:ring-blue-500 focus:ring-offset-0 focus:ring-offset-transparent outline-none" name="status" {{ old('status', $device->status) ? 'checked' : '' }}>
                            <div class="ml-3 flex-1 flex flex-col justify-center">
                                <span class="text-sm text-white/80 font-medium group-hover:text-white transition-colors">
                                    Device Online Status
                                </span>
                                <span class="text-xs text-white/40 font-normal mt-0.5">Toggle allowing telemetry sync. Uncheck to disable data reception from this device.</span>
                            </div>
                        </label>
                    </div>
                    @error('status')
                        <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>
                    @enderror

                    <div class="flex flex-col-reverse sm:flex-row items-center justify-between pt-4 mt-8 border-t border-white/10 gap-4">
                        <a href="{{ route('devices.index') }}" class="w-full sm:w-auto text-center text-white/60 hover:text-white transition-colors text-sm px-4 py-2 flex items-center justify-center">
                            <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Return to List
                        </a>

                        <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 shadow-[0_0_20px_rgba(59,130,246,0.3)] hover:shadow-[0_0_30px_rgba(59,130,246,0.5)] text-white font-bold py-3 px-8 rounded-xl transition-all duration-300 flex items-center justify-center transform hover:-translate-y-0.5">
                            <i data-lucide="save" class="w-5 h-5 mr-2"></i> Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
