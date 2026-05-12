<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fuzzy Logic Tuning - Smart Farming</title>
    
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

    <div class="max-w-4xl mx-auto space-y-6 mt-12 pb-12">
        <header class="text-center mb-8 flex flex-col items-center">
            <div class="flex space-x-4 items-center">
                <div class="w-16 h-16 bg-gradient-to-tr from-purple-500 to-pink-600 rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(168,85,247,0.5)] border border-white/30 mb-4 cursor-default">
                    <i data-lucide="brain-circuit" class="w-8 h-8 text-white drop-shadow-md"></i>
                </div>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight">Fuzzy Parameter Tuning</h1>
            <p class="text-white/50 text-sm mt-2">Adjust membership functions limits for <span class="font-semibold text-white/90">{{ $device->device_name }}</span></p>
        </header>

        @if(session('success'))
            <div class="bg-emerald-500/20 border border-emerald-500/50 text-emerald-200 p-4 rounded-xl mb-6 flex items-center">
                <i data-lucide="check-circle" class="w-5 h-5 mr-3"></i>
                {{ session('success') }}
            </div>
        @endif

        <div class="glass rounded-3xl overflow-hidden shadow-2xl relative p-8">
            <div class="absolute -left-32 -top-32 w-80 h-80 bg-purple-500/10 rounded-full blur-3xl"></div>    

            <form method="POST" action="{{ route('devices.fuzzy.update', $device) }}" class="space-y-8 relative z-10 w-full">
                @csrf
                @method('PUT')

                <!-- Kelembapan Tanah Group -->
                <div class="space-y-4">
                    <h2 class="text-xl font-bold border-b border-white/20 pb-2 flex items-center text-blue-300">
                        <i data-lucide="droplet" class="w-5 h-5 mr-2"></i> Soil Moisture Parameters (%)
                    </h2>
                    <p class="text-xs text-white/50 mb-4">Set boundaries for Dry (Kering), Normal, and Wet (Basah) states (0-100%).</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Kering Max -->
                        <div>
                            <label for="moisture_kering_max" class="block font-medium text-xs text-white/80 mb-2">Max Kering <span class="text-red-400">*</span></label>
                            <input id="moisture_kering_max" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="moisture_kering_max" value="{{ old('moisture_kering_max', $fuzzyConfig->moisture_kering_max) }}" required />
                            @error('moisture_kering_max') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Normal Min -->
                        <div>
                            <label for="moisture_normal_min" class="block font-medium text-xs text-white/80 mb-2">Min Normal <span class="text-red-400">*</span></label>
                            <input id="moisture_normal_min" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="moisture_normal_min" value="{{ old('moisture_normal_min', $fuzzyConfig->moisture_normal_min) }}" required />
                            @error('moisture_normal_min') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Normal Max -->
                        <div>
                            <label for="moisture_normal_max" class="block font-medium text-xs text-white/80 mb-2">Max Normal <span class="text-red-400">*</span></label>
                            <input id="moisture_normal_max" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="moisture_normal_max" value="{{ old('moisture_normal_max', $fuzzyConfig->moisture_normal_max) }}" required />
                            @error('moisture_normal_max') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Basah Min -->
                        <div>
                            <label for="moisture_basah_min" class="block font-medium text-xs text-white/80 mb-2">Min Basah <span class="text-red-400">*</span></label>
                            <input id="moisture_basah_min" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="moisture_basah_min" value="{{ old('moisture_basah_min', $fuzzyConfig->moisture_basah_min) }}" required />
                            @error('moisture_basah_min') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <!-- pH Tanah Group -->
                <div class="space-y-4 pt-4">
                    <h2 class="text-xl font-bold border-b border-white/20 pb-2 flex items-center text-emerald-300">
                        <i data-lucide="flask-conical" class="w-5 h-5 mr-2"></i> Soil pH Parameters
                    </h2>
                    <p class="text-xs text-white/50 mb-4">Set boundaries for Acidic (Asam), Neutral (Netral), and Alkaline (Basa) states (0-14).</p>

                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <!-- Asam Max -->
                        <div>
                            <label for="ph_asam_max" class="block font-medium text-xs text-white/80 mb-2">Max Asam <span class="text-red-400">*</span></label>
                            <input id="ph_asam_max" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="ph_asam_max" value="{{ old('ph_asam_max', $fuzzyConfig->ph_asam_max) }}" required />
                            @error('ph_asam_max') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Netral Min -->
                        <div>
                            <label for="ph_netral_min" class="block font-medium text-xs text-white/80 mb-2">Min Netral <span class="text-red-400">*</span></label>
                            <input id="ph_netral_min" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="ph_netral_min" value="{{ old('ph_netral_min', $fuzzyConfig->ph_netral_min) }}" required />
                            @error('ph_netral_min') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Netral Max -->
                        <div>
                            <label for="ph_netral_max" class="block font-medium text-xs text-white/80 mb-2">Max Netral <span class="text-red-400">*</span></label>
                            <input id="ph_netral_max" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="ph_netral_max" value="{{ old('ph_netral_max', $fuzzyConfig->ph_netral_max) }}" required />
                            @error('ph_netral_max') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>

                        <!-- Basa Min -->
                        <div>
                            <label for="ph_basa_min" class="block font-medium text-xs text-white/80 mb-2">Min Basa <span class="text-red-400">*</span></label>
                            <input id="ph_basa_min" class="input-glass block w-full px-4 py-2.5 rounded-xl focus:ring-0 text-center" type="number" step="0.1" name="ph_basa_min" value="{{ old('ph_basa_min', $fuzzyConfig->ph_basa_min) }}" required />
                            @error('ph_basa_min') <span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span> @enderror
                        </div>
                    </div>
                </div>

                <div class="flex flex-col-reverse sm:flex-row items-center justify-between pt-6 mt-8 border-t border-white/10 gap-4">
                    <a href="{{ route('devices.index') }}" class="w-full sm:w-auto text-center text-white/60 hover:text-white transition-colors text-sm px-4 py-2 flex items-center justify-center">
                        <i data-lucide="arrow-left" class="w-4 h-4 mr-2"></i> Back to Devices
                    </a>

                    <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-500 hover:to-pink-500 shadow-[0_0_20px_rgba(168,85,247,0.3)] hover:shadow-[0_0_30px_rgba(168,85,247,0.5)] text-white font-bold py-3 px-8 rounded-xl transition-all duration-300 flex items-center justify-center transform hover:-translate-y-0.5">
                        <i data-lucide="save" class="w-5 h-5 mr-2"></i> Save Parameters
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
