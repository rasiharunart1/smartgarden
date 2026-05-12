<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Devices - Smart Farming</title>
    
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

        /* Override default autocomplete styling which turns input bg white */
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

    <!-- Top Navigation / Controls -->
    <div class="max-w-7xl mx-auto mb-6 flex justify-between items-center bg-black/20 p-4 rounded-2xl glass">
        <div class="flex items-center space-x-4">
            <a href="{{ route('profile.edit') }}" class="flex items-center space-x-2 hover:text-emerald-400 transition">
                <i data-lucide="user" class="w-5 h-5"></i>
                <span class="font-medium hidden sm:inline">{{ Auth::user()->name }}</span>
            </a>
            <span class="text-white/30 hidden sm:inline">|</span>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-sm hover:text-red-400 text-white/70 transition flex items-center">
                    <i data-lucide="log-out" class="w-4 h-4 mr-1"></i> Logout
                </button>
            </form>
        </div>

        <div class="flex items-center space-x-3 overflow-x-auto">
            <a href="{{ route('dashboard') }}" class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="layout-dashboard" class="w-4 h-4 mr-1"></i> Dashboard
            </a>
            <a href="{{ route('devices.index') }}" class="glass border-emerald-500/50 rounded-lg px-4 py-2 text-white text-sm bg-emerald-500/20 transition flex items-center shadow-lg shadow-emerald-500/20">
                <i data-lucide="hard-drive" class="w-4 h-4 mr-2"></i> Devices
            </a>
            <a href="{{ route('logs.index') }}" class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="history" class="w-4 h-4 mr-1"></i> Logs
            </a>
        </div>
    </div>

    <div class="max-w-6xl mx-auto space-y-6">
        
        <!-- Header -->
        <header class="glass rounded-3xl p-6 md:p-8 flex flex-col md:flex-row gap-8 items-center justify-between cursor-default">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg border border-white/30">
                    <i data-lucide="server" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight">Manage Devices</h1>
                    <p class="text-white/60 text-sm mt-1">Configure and manage your IoT edge devices</p>
                </div>
            </div>
            
            <a href="{{ route('devices.create') }}" class="inline-flex items-center justify-center bg-emerald-500 hover:bg-emerald-600 focus:ring-4 focus:ring-emerald-500/50 text-white font-bold py-3 px-6 rounded-full transition shadow-[0_0_20px_rgba(16,185,129,0.4)]">
                <i data-lucide="plus" class="w-5 h-5 mr-2"></i> Add New Device
            </a>
        </header>

        <!-- Alerts -->
        @if(session('success'))
            <div class="glass rounded-2xl p-4 border border-emerald-500/30 bg-emerald-500/10 flex items-center text-emerald-300">
                <i data-lucide="check-circle-2" class="w-5 h-5 mr-3"></i>
                {{ session('success') }}
            </div>
        @endif

        <!-- Table -->
        <div class="glass rounded-3xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-white/10 bg-black/20 text-white/50 text-xs uppercase tracking-wider">
                            <th class="p-6 font-semibold">Device Name</th>
                            <th class="p-6 font-semibold">API / Device Code</th>
                            <th class="p-6 font-semibold">Location</th>
                            <th class="p-6 font-semibold">Status</th>
                            <th class="p-6 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/10 text-sm">
                        @forelse ($devices as $device)
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="p-6 align-middle font-medium flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center border border-white/20">
                                        <i data-lucide="cpu" class="w-4 h-4 text-emerald-400"></i>
                                    </div>
                                    {{ $device->device_name }}
                                </td>
                                <td class="p-6 align-middle text-white/70">
                                    <code class="bg-black/30 px-3 py-1.5 rounded-lg border border-white/10 text-xs font-mono select-all text-emerald-300">{{ $device->device_code }}</code>
                                </td>
                                <td class="p-6 align-middle text-white/70 flex items-center">
                                    <i data-lucide="map-pin" class="w-4 h-4 mr-1.5 opacity-50"></i> {{ $device->location ?? 'Not specified' }}
                                </td>
                                <td class="p-6 align-middle">
                                    @if($device->is_online)
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 bg-emerald-400 rounded-full mr-1.5 animate-pulse"></span> Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-500/20 text-red-300 border border-red-500/30">
                                            <span class="w-1.5 h-1.5 bg-red-400 rounded-full mr-1.5"></span> Offline
                                        </span>
                                    @endif
                                </td>
                                <td class="p-6 align-middle text-right flex justify-end gap-3 items-center">
                                    <a href="{{ route('devices.fuzzy', $device) }}" class="p-2 bg-white/5 hover:bg-purple-500/20 hover:text-purple-400 hover:border-purple-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Fuzzy Tuning">
                                        <i data-lucide="brain-circuit" class="w-4 h-4"></i>
                                    </a>
                                    
                                    <a href="{{ route('devices.edit', $device) }}" class="p-2 bg-white/5 hover:bg-blue-500/20 hover:text-blue-400 hover:border-blue-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Edit">
                                        <i data-lucide="edit-3" class="w-4 h-4"></i>
                                    </a>
                                    
                                    <form action="{{ route('devices.destroy', $device) }}" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to delete this device? All IoT telemetry logs under this device will be permanently deleted.');" class="inline m-0 flex items-center">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="p-2 bg-white/5 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/30 border border-transparent rounded-xl transition-all text-white/60" title="Delete">
                                            <i data-lucide="trash-2" class="w-4 h-4"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="p-12 text-center text-white/50">
                                    <div class="flex flex-col items-center justify-center">
                                        <i data-lucide="search-x" class="w-12 h-12 mb-4 opacity-50"></i>
                                        <p>No devices found. Click "Add New Device" to create your first IoT endpoint.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            @if ($devices->hasPages())
                <div class="p-4 border-t border-white/10 bg-black/10">
                    {{ $devices->links() }}
                </div>
            @endif
        </div>
    </div>
    
    <!-- Scripts for Icons -->
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
