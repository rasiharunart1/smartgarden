<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Devices - SENA Smart Garden</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;600;800&display=swap');
        body{font-family:'Plus Jakarta Sans',sans-serif;background:linear-gradient(135deg,#1e1b4b 0%,#064e3b 50%,#0d9488 100%);min-height:100vh}
        .glass{background:rgba(255,255,255,0.1);backdrop-filter:blur(12px);border:1px solid rgba(255,255,255,0.2);box-shadow:0 8px 32px rgba(0,0,0,0.37)}
    </style>
</head>
<body class="text-white p-3 sm:p-4 md:p-8">

    <!-- Top Navigation (2-row mobile) -->
    <div class="max-w-7xl mx-auto mb-4">
        <div class="flex justify-between items-center bg-black/20 px-4 py-3 rounded-t-2xl glass border-b border-white/10">
            <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 hover:text-emerald-400 transition">
                <img src="{{ Auth::user()->avatar_url }}" class="w-8 h-8 rounded-full object-cover border border-white/20 flex-shrink-0" alt="avatar">
                <span class="font-medium text-sm truncate max-w-[120px] sm:max-w-none">{{ Auth::user()->name }}</span>
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
            <a href="{{ route('devices.index') }}" class="flex-1 sm:flex-none text-white text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                <i class="fa-solid fa-microchip mr-1"></i><span class="hidden sm:inline">Devices</span>
            </a>
            <a href="{{ route('logs.index') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i><span class="hidden sm:inline">Logs</span>
            </a>
        </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-4 sm:space-y-6">

        <!-- Header -->
        <header class="glass rounded-3xl p-4 sm:p-6 md:p-8 flex flex-col sm:flex-row gap-4 sm:gap-8 items-center justify-between">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 sm:w-16 sm:h-16 bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg border border-white/30 flex-shrink-0">
                    <i class="fa-solid fa-server text-2xl text-white"></i>
                </div>
                <div>
                    <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight">Manage Devices</h1>
                    <p class="text-white/60 text-xs sm:text-sm mt-1">Configure and manage your IoT edge devices</p>
                </div>
            </div>
            <a href="{{ route('devices.create') }}" class="w-full sm:w-auto flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-6 rounded-full transition shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                <i class="fa-solid fa-plus mr-2"></i> Add New Device
            </a>
        </header>

        <!-- Alerts -->
        @if(session('success'))
            <div class="glass rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/10 flex items-center text-emerald-300 text-sm">
                <i class="fa-solid fa-circle-check mr-3"></i> {{ session('success') }}
            </div>
        @endif

        {{-- ── MOBILE CARD VIEW (< md) ── --}}
        <div class="md:hidden space-y-3">
            @forelse($devices as $device)
                <div class="glass rounded-2xl p-4 space-y-3">
                    <div class="flex items-center gap-3">
                        <img src="{{ $device->photo_url }}" alt="{{ $device->device_name }}"
                             class="w-12 h-12 rounded-xl object-cover border border-white/20 flex-shrink-0">
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm truncate">{{ $device->device_name }}</p>
                            <p class="text-white/50 text-xs flex items-center mt-0.5">
                                <i class="fa-solid fa-location-dot mr-1 text-emerald-400/70"></i>
                                {{ $device->location ?? 'Not specified' }}
                            </p>
                        </div>
                        @if($device->is_online)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 flex-shrink-0">
                                <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-1 animate-pulse"></span>Active
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-500/20 text-red-300 border border-red-500/30 flex-shrink-0">
                                <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1"></span>Offline
                            </span>
                        @endif
                    </div>
                    <code class="block bg-black/30 px-3 py-2 rounded-lg border border-white/10 text-xs font-mono text-emerald-300 select-all truncate">{{ $device->device_code }}</code>
                    <div class="flex items-center gap-2 pt-1">
                        <a href="{{ route('devices.fuzzy', $device) }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-purple-500/10 hover:bg-purple-500/20 text-purple-300 border border-purple-500/30 rounded-xl text-xs transition">
                            <i class="fa-solid fa-brain"></i> Fuzzy
                        </a>
                        <a href="{{ route('devices.edit', $device) }}" class="flex-1 flex items-center justify-center gap-1.5 py-2 bg-blue-500/10 hover:bg-blue-500/20 text-blue-300 border border-blue-500/30 rounded-xl text-xs transition">
                            <i class="fa-solid fa-pen-to-square"></i> Edit
                        </a>
                        <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Hapus device ini?')" class="flex-1">
                            @csrf @method('DELETE')
                            <button type="submit" class="w-full flex items-center justify-center gap-1.5 py-2 bg-red-500/10 hover:bg-red-500/20 text-red-300 border border-red-500/30 rounded-xl text-xs transition">
                                <i class="fa-solid fa-trash"></i> Hapus
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <div class="glass rounded-2xl p-10 text-center text-white/50">
                    <i class="fa-solid fa-magnifying-glass text-4xl mb-3 block opacity-30"></i>
                    <p class="text-sm">Belum ada device. Klik "Add New Device" untuk menambahkan perangkat IoT pertama.</p>
                </div>
            @endforelse
        </div>

        {{-- ── DESKTOP TABLE VIEW (≥ md) ── --}}
        <div class="hidden md:block glass rounded-3xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 bg-black/20 text-white/50 text-xs uppercase tracking-wider">
                            <th class="p-6 font-semibold">Device</th>
                            <th class="p-6 font-semibold">API / Device Code</th>
                            <th class="p-6 font-semibold">Location</th>
                            <th class="p-6 font-semibold">Status</th>
                            <th class="p-6 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-sm">
                        @forelse ($devices as $device)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-6 align-middle font-medium">
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $device->photo_url }}" alt="{{ $device->device_name }}"
                                             class="w-10 h-10 rounded-xl object-cover border border-white/20 flex-shrink-0">
                                        <span>{{ $device->device_name }}</span>
                                    </div>
                                </td>
                                <td class="p-6 align-middle text-white/70">
                                    <code class="bg-black/30 px-3 py-1.5 rounded-lg border border-white/10 text-xs font-mono select-all text-emerald-300">{{ $device->device_code }}</code>
                                </td>
                                <td class="p-6 align-middle text-white/70">
                                    <i class="fa-solid fa-location-dot mr-1.5 opacity-50"></i>{{ $device->location ?? 'Not specified' }}
                                </td>
                                <td class="p-6 align-middle">
                                    @if($device->is_online)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-1.5 animate-pulse"></span>Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-300 border border-red-500/30">
                                            <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></span>Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="p-6 align-middle">
                                    <div class="flex justify-end gap-2 items-center">
                                        <a href="{{ route('devices.fuzzy', $device) }}" class="p-2 bg-white/5 hover:bg-purple-500/20 hover:text-purple-400 hover:border-purple-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Fuzzy Config">
                                            <i class="fa-solid fa-brain fa-sm"></i>
                                        </a>
                                        <a href="{{ route('devices.edit', $device) }}" class="p-2 bg-white/5 hover:bg-blue-500/20 hover:text-blue-400 hover:border-blue-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Edit">
                                            <i class="fa-solid fa-pen-to-square fa-sm"></i>
                                        </a>
                                        <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('Hapus device ini? Semua data telemetri akan ikut terhapus.')" class="inline m-0">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="p-2 bg-white/5 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Delete">
                                                <i class="fa-solid fa-trash fa-sm"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-12 text-center text-white/50">
                                    <i class="fa-solid fa-magnifying-glass text-5xl mb-4 opacity-30 block"></i>
                                    <p>Belum ada device. Klik "Add New Device" untuk menambahkan perangkat IoT pertama.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($devices->hasPages())
                <div class="p-4 border-t border-white/10 bg-black/10">{{ $devices->links() }}</div>
            @endif
        </div>

        {{-- Pagination mobile --}}
        @if ($devices->hasPages())
            <div class="md:hidden">{{ $devices->links() }}</div>
        @endif
    </div>
</body>
</html>
