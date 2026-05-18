<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Device - SENA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#1e1b4b 0%,#064e3b 50%,#0d9488 100%);min-height:100vh}
        .glass{background:rgba(255,255,255,0.1);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.2);box-shadow:0 8px 32px rgba(0,0,0,0.37)}
        .ig{background:rgba(0,0,0,0.1);border:1px solid rgba(255,255,255,0.1);color:white;transition:all .3s}
        .ig:focus{background:rgba(0,0,0,0.2);border-color:rgba(16,185,129,0.5);outline:none;box-shadow:0 0 0 2px rgba(16,185,129,0.2)}
    </style>
</head>
<body class="text-white p-4 md:p-8">
    <!-- Top Navigation -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="flex justify-between items-center bg-black/20 px-4 py-3 rounded-t-2xl glass border-b border-white/10">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 hover:text-emerald-400 transition">
                <img src="{{ Auth::user()->avatar_url }}" class="w-8 h-8 rounded-full object-cover border border-white/20 flex-shrink-0" alt="avatar">
                <span class="font-medium text-sm truncate max-w-[120px] sm:max-w-none">{{ Auth::user()->name }}</span>
            </a>
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

    <div class="max-w-3xl mx-auto mt-8">
        <header class="text-center mb-8">
            <div class="w-20 h-20 mx-auto bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg border border-white/30 mb-4 hover:scale-105 transition-all">
                <i class="fa-solid fa-circle-plus text-3xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold">Register New API Device</h1>
            <p class="text-white/60 text-sm mt-2">Add a new board (e.g. ESP32) and generate its secure API Key.</p>
        </header>

        <div class="glass rounded-3xl p-8 shadow-2xl relative overflow-hidden">
            <div class="absolute -right-20 -top-20 w-64 h-64 bg-emerald-500/10 rounded-full blur-3xl"></div>
            <form method="POST" action="{{ route('devices.store') }}" class="space-y-6 relative z-10" enctype="multipart/form-data">
                @csrf

                {{-- Photo --}}
                <div>
                    <label class="block text-sm text-white/80 font-medium mb-3"><i class="fa-solid fa-image mr-1 text-emerald-400"></i> Device Photo (Opsional)</label>
                    <div class="flex items-center gap-5">
                        <div class="w-20 h-20 rounded-2xl bg-white/10 border-2 border-dashed border-white/20 flex items-center justify-center overflow-hidden flex-shrink-0">
                            <i class="fa-solid fa-camera text-2xl text-white/30" id="p-icon"></i>
                            <img id="p-img" src="" class="w-full h-full object-cover hidden" alt="">
                        </div>
                        <div>
                            <label for="photo" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl text-sm transition">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i> Pilih Foto
                            </label>
                            <input id="photo" name="photo" type="file" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                            <p class="text-xs text-white/40 mt-2">JPG, PNG, WebP. Maks 2MB.</p>
                            @error('photo')<span class="text-red-400 text-xs block mt-1">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>

                {{-- Device Name --}}
                <div>
                    <label for="device_name" class="block text-sm text-white/80 font-medium mb-2">Device Name <span class="text-red-400">*</span></label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-microchip text-emerald-400/70"></i></span>
                        <input id="device_name" class="ig block w-full pl-12 pr-4 py-3 rounded-xl" type="text" name="device_name" value="{{ old('device_name') }}" required autofocus placeholder="e.g. GreenHouse Node #1">
                    </div>
                    @error('device_name')<span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>@enderror
                </div>

                {{-- Location --}}
                <div>
                    <label for="location" class="block text-sm text-white/80 font-medium mb-2">Location (Optional)</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-location-dot text-emerald-400/70"></i></span>
                        <input id="location" class="ig block w-full pl-12 pr-4 py-3 rounded-xl" type="text" name="location" value="{{ old('location') }}" placeholder="e.g. Block A / Garden West">
                    </div>
                    @error('location')<span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>@enderror
                </div>

                {{-- Status --}}
                <div class="bg-black/10 rounded-xl p-4 border border-white/5">
                    <label for="status" class="inline-flex items-center cursor-pointer group">
                        <input id="status" type="checkbox" name="status" value="1" class="w-5 h-5 rounded text-emerald-500" {{ old('status', true) ? 'checked' : '' }}>
                        <span class="ml-3 text-sm text-white/80 font-medium group-hover:text-white transition">Initialize as Active
                            <p class="text-xs text-white/40 font-normal mt-0.5">Allow device to push telemetry immediately upon creation.</p>
                        </span>
                    </label>
                </div>

                <div class="flex items-center justify-between pt-4 mt-4 border-t border-white/10">
                    <a href="{{ route('devices.index') }}" class="text-white/60 hover:text-white text-sm px-4 py-2 flex items-center">
                        <i class="fa-solid fa-arrow-left mr-2"></i> Cancel
                    </a>
                    <button type="submit" class="bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-bold py-3 px-8 rounded-xl transition-all flex items-center shadow-[0_0_20px_rgba(16,185,129,0.3)]">
                        <i class="fa-solid fa-floppy-disk mr-2"></i> Create API Device
                    </button>
                </div>
            </form>
        </div>
    </div>
    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => {
                    document.getElementById('p-img').src = e.target.result;
                    document.getElementById('p-img').classList.remove('hidden');
                    document.getElementById('p-icon').classList.add('hidden');
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
