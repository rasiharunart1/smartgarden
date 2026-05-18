<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telemetry Logs - Smart Farming</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
        
        /* Pagination overrides to match dark theme */
        .pagination {
            display: flex;
            padding-left: 0;
            list-style: none;
        }
        .page-link {
            position: relative;
            display: block;
            color: #6ee7b7; /* emerald-300 */
            text-decoration: none;
            background-color: rgba(0,0,0,0.3);
            border: 1px solid rgba(255,255,255,0.1);
            transition: color .15s ease-in-out,background-color .15s ease-in-out,border-color .15s ease-in-out,box-shadow .15s ease-in-out;
        }
        .page-link:hover {
            z-index: 2;
            color: #10b981; /* emerald-500 */
            background-color: rgba(255,255,255,0.1);
            border-color: rgba(255,255,255,0.2);
        }
        .page-item.active .page-link {
            z-index: 3;
            color: #fff;
            background-color: #10b981;
            border-color: #10b981;
        }
        .page-item.disabled .page-link {
            color: rgba(255,255,255,0.3);
            pointer-events: none;
            background-color: rgba(0,0,0,0.1);
            border-color: rgba(255,255,255,0.05);
        }
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
            <a href="{{ route('logs.index') }}" class="flex-1 sm:flex-none text-white text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i><span class="hidden sm:inline">Logs</span>
            </a>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-6">
        
        <!-- Header -->
        <header class="glass rounded-3xl p-6 md:p-8 flex flex-col md:flex-row gap-6 items-center justify-between cursor-default shadow-lg">
            <div class="flex items-center gap-4">
                <div class="w-16 h-16 bg-gradient-to-tr from-teal-400 to-emerald-600 rounded-2xl flex items-center justify-center shadow-[0_0_20px_rgba(20,184,166,0.4)] border border-white/30">
                    <i data-lucide="database" class="w-8 h-8 text-white"></i>
                </div>
                <div>
                    <h1 class="text-3xl font-extrabold tracking-tight">System Telemetry Logs</h1>
                    <p class="text-white/60 text-sm mt-1">Review historical sensor data recorded across your devices.</p>
                </div>
            </div>

            <!-- Filter & Export -->
            @if($devices->count() > 0)
            <div class="w-full md:w-auto flex flex-col sm:flex-row items-center gap-3">
                <form action="{{ route('logs.index') }}" method="GET" class="flex items-center space-x-2 bg-black/20 p-2 rounded-xl border border-white/10 w-full sm:w-auto">
                    <i data-lucide="filter" class="w-4 h-4 text-white/50 ml-2"></i>
                    <select name="device_id" onchange="this.form.submit()" class="bg-transparent text-white border-none text-sm focus:ring-0 appearance-none cursor-pointer pr-8 py-1">
                        <option class="text-black" value="">All Devices</option>
                        @foreach($devices as $dev)
                            <option class="text-black" value="{{ $dev->id }}" {{ request('device_id') == $dev->id ? 'selected' : '' }}>
                                {{ $dev->device_name }}
                            </option>
                        @endforeach
                    </select>
                </form>

                <a href="{{ route('logs.export', ['device_id' => request('device_id')]) }}" class="w-full sm:w-auto flex items-center justify-center space-x-2 bg-emerald-500 hover:bg-emerald-400 text-white p-2 px-4 rounded-xl shadow-lg shadow-emerald-500/20 transition-all text-sm font-semibold">
                    <i data-lucide="download" class="w-4 h-4"></i>
                    <span>Export CSV</span>
                </a>
            </div>
            @endif
        </header>

        <!-- Table Data -->
        <div class="glass rounded-3xl overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse whitespace-nowrap">
                    <thead>
                        <tr class="border-b border-white/10 bg-black/30 text-white/50 text-[11px] font-bold uppercase tracking-widest">
                            <th class="p-5">Timestamp <i data-lucide="clock" class="inline w-3 h-3 ml-1 mb-0.5"></i></th>
                            <th class="p-5">Device Name</th>
                            <th class="p-5 text-center">Temp (°C)</th>
                            <th class="p-5 text-center">Air Humid (%)</th>
                            <th class="p-5 text-center">Soil Humid (%)</th>
                            <th class="p-5 text-center">Light (Lux)</th>
                            <th class="p-5 text-center">pH</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-white/5 text-sm font-medium">
                        @forelse ($logs as $log)
                            <tr class="hover:bg-white/5 transition-colors group">
                                <td class="p-5 align-middle text-white/80 font-mono text-xs">
                                    {{ $log->created_at->format('Y-m-d H:i:s') }}
                                </td>
                                <td class="p-5 align-middle flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full {{ $log->device->status ? 'bg-emerald-400' : 'bg-red-400' }}"></span>
                                    {{ $log->device->device_name }}
                                </td>
                                <td class="p-5 align-middle text-center text-orange-300">
                                    {{ $log->temperature ?? '--' }}
                                </td>
                                <td class="p-5 align-middle text-center text-indigo-300">
                                    {{ $log->humidity ?? '--' }}
                                </td>
                                <td class="p-5 align-middle text-center text-cyan-300">
                                    {{ $log->soil_moisture ?? '--' }}
                                </td>
                                <td class="p-5 align-middle text-center text-amber-300">
                                    {{ $log->light_intensity ?? '--' }}
                                </td>
                                <td class="p-5 align-middle text-center text-blue-300">
                                    {{ $log->ph ?? '--' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="p-16 text-center text-white/50">
                                    <div class="flex flex-col items-center justify-center">
                                        <div class="w-16 h-16 bg-white/5 rounded-full flex items-center justify-center mb-4 border border-white/10">
                                            <i data-lucide="database-zap" class="w-8 h-8 opacity-50"></i>
                                        </div>
                                        <p class="text-lg font-semibold text-white/70">No Telemetry Data Found</p>
                                        <p class="text-sm mt-1">Make sure your IoT devices are configured and transmitting data correctly to the API.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Control (Leveraging Tailwind generic rendering with custom CSS overrides) -->
            @if ($logs->hasPages())
                <div class="p-5 border-t border-white/10 bg-black/20 text-sm">
                    {{ $logs->links('pagination::tailwind') }}
                </div>
            @endif
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
</body>
</html>
