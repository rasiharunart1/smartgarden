<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Device - SENA</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#1e1b4b 0%,#064e3b 50%,#0d9488 100%);min-height:100vh}
        .glass{background:rgba(255,255,255,0.1);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.2);box-shadow:0 8px 32px rgba(0,0,0,0.37)}
        .ig{background:rgba(0,0,0,0.1);border:1px solid rgba(255,255,255,0.1);color:white;transition:all .3s}
        .ig:focus{background:rgba(0,0,0,0.2);border-color:rgba(59,130,246,0.5);outline:none;box-shadow:0 0 0 2px rgba(59,130,246,0.2)}
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
            <div class="w-16 h-16 mx-auto bg-gradient-to-tr from-blue-500 to-indigo-600 rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(59,130,246,0.5)] border border-white/30 mb-4">
                <i class="fa-solid fa-pen-to-square text-2xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold">Edit Device</h1>
            <p class="text-white/50 text-sm mt-2">Updating settings for <span class="font-semibold text-white/90">{{ $device->device_name }}</span></p>
        </header>

        <div class="glass rounded-3xl overflow-hidden shadow-2xl relative">
            <div class="absolute -right-32 -top-32 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl"></div>

            {{-- API Code Banner --}}
            <div class="bg-black/30 p-6 border-b border-white/10 relative z-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div>
                    <h3 class="text-xs font-bold uppercase tracking-wider text-blue-400 mb-1"><i class="fa-solid fa-key mr-1"></i> API Connection Code</h3>
                    <p class="text-xs text-white/50">Inject this UUID header (<code>X-Device-Code</code>) in your HTTP POST requests.</p>
                </div>
                <div class="bg-black/50 border border-white/10 rounded-lg p-3 flex items-center w-full sm:w-auto overflow-hidden group cursor-pointer"
                     onclick="navigator.clipboard.writeText('{{ $device->device_code }}').then(()=>alert('Copied!'))" title="Click to copy">
                    <code class="font-mono text-sm text-emerald-300 truncate select-all">{{ $device->device_code }}</code>
                    <i class="fa-solid fa-copy ml-3 text-white/30 group-hover:text-white transition flex-shrink-0"></i>
                </div>
            </div>

            <div class="p-8 relative z-10">
                <form method="POST" action="{{ route('devices.update', $device) }}" class="space-y-6" enctype="multipart/form-data">
                    @csrf @method('PUT')

                    {{-- Photo --}}
                    <div>
                        <label class="block text-sm text-white/80 font-medium mb-3"><i class="fa-solid fa-image mr-1 text-blue-400"></i> Device Photo</label>
                        <div class="flex items-center gap-5">
                            <div class="w-20 h-20 rounded-2xl border-2 border-white/20 overflow-hidden flex-shrink-0">
                                <img id="p-img" src="{{ $device->photo_url }}" alt="{{ $device->device_name }}" class="w-full h-full object-cover">
                            </div>
                            <div class="space-y-2">
                                <label for="photo" class="cursor-pointer inline-flex items-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 border border-white/20 rounded-xl text-sm transition">
                                    <i class="fa-solid fa-arrow-up-from-bracket"></i> Ganti Foto
                                </label>
                                <input id="photo" name="photo" type="file" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                                @if($device->photo)
                                <label class="flex items-center gap-2 text-xs text-red-400/80 cursor-pointer hover:text-red-400">
                                    <input type="checkbox" name="remove_photo" value="1" class="rounded"> Hapus foto saat ini
                                </label>
                                @endif
                                <p class="text-xs text-white/40">JPG, PNG, WebP. Maks 2MB.</p>
                                @error('photo')<span class="text-red-400 text-xs block">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    </div>

                    {{-- Device Name --}}
                    <div>
                        <label for="device_name" class="block text-sm text-white/80 font-medium mb-2">Device Name <span class="text-red-400">*</span></label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-microchip text-blue-400/70"></i></span>
                            <input id="device_name" class="ig block w-full pl-12 pr-4 py-3 rounded-xl" type="text" name="device_name" value="{{ old('device_name', $device->device_name) }}" required autofocus>
                        </div>
                        @error('device_name')<span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>@enderror
                    </div>

                    {{-- Location --}}
                    <div>
                        <label for="location" class="block text-sm text-white/80 font-medium mb-2">Location (Optional)</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none"><i class="fa-solid fa-location-dot text-blue-400/70"></i></span>
                            <input id="location" class="ig block w-full pl-12 pr-4 py-3 rounded-xl" type="text" name="location" value="{{ old('location', $device->location) }}">
                        </div>
                        @error('location')<span class="text-red-400 text-xs mt-1 block px-2">{{ $message }}</span>@enderror
                    </div>

                    {{-- Status --}}
                    <div class="bg-black/10 rounded-xl p-4 border border-white/5">
                        <label for="status" class="inline-flex items-center cursor-pointer group w-full">
                            <input id="status" type="checkbox" name="status" value="1" class="w-5 h-5 rounded text-blue-500" {{ old('status', $device->status) ? 'checked' : '' }}>
                            <div class="ml-3">
                                <span class="text-sm text-white/80 font-medium group-hover:text-white transition">Device Online Status</span>
                                <p class="text-xs text-white/40 mt-0.5">Uncheck to disable telemetry reception from this device.</p>
                            </div>
                        </label>
                    </div>

                    <div class="flex flex-col-reverse sm:flex-row items-center justify-between pt-4 mt-4 border-t border-white/10 gap-4">
                        <a href="{{ route('devices.index') }}" class="w-full sm:w-auto text-center text-white/60 hover:text-white text-sm px-4 py-2 flex items-center justify-center">
                            <i class="fa-solid fa-arrow-left mr-2"></i> Return to List
                        </a>
                        <button type="submit" class="w-full sm:w-auto bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3 px-8 rounded-xl transition-all flex items-center justify-center shadow-[0_0_20px_rgba(59,130,246,0.3)]">
                            <i class="fa-solid fa-floppy-disk mr-2"></i> Update Settings
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function previewPhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = e => { document.getElementById('p-img').src = e.target.result; };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>
</body>
</html>
