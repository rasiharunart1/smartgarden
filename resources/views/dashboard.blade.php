<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Farming Dashboard - Glassmorphism</title>

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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

        .progress-ring {
            transition: stroke-dashoffset 0.35s;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }

        @keyframes spin-slow {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .animate-spin-slow {
            animation: spin-slow 8s linear infinite;
        }

        .pump-active {
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.5);
        }
    </style>
</head>

<body class="text-white p-4 md:p-8">

    <!-- Top Navigation -->
    <div class="max-w-7xl mx-auto mb-4">
        <!-- Row 1: User + Logout -->
        <div class="flex justify-between items-center bg-black/20 px-4 py-3 rounded-t-2xl glass border-b border-white/10">
            <div class="flex items-center gap-3">
                <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 hover:text-emerald-400 transition">
                    <img src="{{ Auth::user()->avatar_url }}" class="w-8 h-8 rounded-full object-cover border border-white/20 flex-shrink-0" alt="avatar">
                    <span class="font-medium text-sm truncate max-w-[120px] sm:max-w-none">{{ Auth::user()->name }}</span>
                </a>
            </div>
            <div class="flex items-center gap-2">
                @if($devices->count() > 0)
                    <form action="{{ route('dashboard') }}" method="GET">
                        <select name="device_id" onchange="this.form.submit()"
                            class="glass rounded-lg text-white bg-transparent border-white/30 px-3 py-1.5 text-xs sm:text-sm focus:ring-emerald-500 appearance-none max-w-[140px] sm:max-w-none">
                            @foreach($devices as $dev)
                                <option class="text-black" value="{{ $dev->id }}" {{ ($activeDevice && $activeDevice->id == $dev->id) ? 'selected' : '' }}>
                                    {{ $dev->device_name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-xs sm:text-sm hover:text-red-400 text-white/70 transition flex items-center">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="hidden sm:inline ml-1">Logout</span>
                    </button>
                </form>
            </div>
        </div>
        <!-- Row 2: Navigation links -->
        <div class="flex items-center gap-1 bg-black/10 px-4 py-2 rounded-b-2xl glass">
            <a href="{{ route('dashboard') }}" class="flex-1 sm:flex-none text-white text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg bg-emerald-500/20 border border-emerald-500/30">
                <i class="fa-solid fa-gauge mr-1"></i> <span class="hidden sm:inline">Dashboard</span>
            </a>
            <a href="{{ route('devices.index') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-microchip mr-1"></i> <span class="hidden sm:inline">Devices</span>
            </a>
            <a href="{{ route('logs.index') }}" class="flex-1 sm:flex-none text-white/70 hover:text-emerald-400 text-xs sm:text-sm flex items-center justify-center sm:justify-start px-3 py-1.5 rounded-lg hover:bg-white/10 transition">
                <i class="fa-solid fa-clock-rotate-left mr-1"></i> <span class="hidden sm:inline">Logs</span>
            </a>
        </div>
    </div>

    @if(!$activeDevice)
        <div class="max-w-6xl mx-auto glass rounded-3xl p-12 text-center mt-12">
            <i class="fa-solid fa-circle-exclamation text-8xl mx-auto mb-6 text-amber-400 block"></i>
            <h2 class="text-3xl font-bold mb-4">No Devices Configured</h2>
            <p class="text-white/70 mb-8 max-w-md mx-auto">You need to add a device to start monitoring and receiving
                telemetry data.</p>
            <a href="{{ route('devices.create') }}"
                class="inline-block bg-emerald-500 hover:bg-emerald-600 shadow-[0_0_20px_rgba(16,185,129,0.4)] text-white font-bold py-3 px-8 rounded-full transition">Add
                Device Now</a>
        </div>
    @else
        <div class="max-w-6xl mx-auto space-y-6">

            <!-- Header / Device Info -->
            <header class="glass rounded-3xl p-4 sm:p-6 md:p-8 flex flex-col sm:flex-row gap-4 sm:gap-8 items-center cursor-default">
                <div class="w-24 h-24 sm:w-32 sm:h-32 md:w-40 md:h-40 rounded-2xl shadow-lg border border-white/30 relative overflow-hidden group flex-shrink-0">
                    <img src="{{ $activeDevice->photo_url }}"
                         alt="{{ $activeDevice->device_name }}"
                         class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-500">
                    <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity duration-300"></div>
                </div>
                <div class="flex-1 text-center md:text-left">
                    <div class="flex flex-col md:flex-row md:items-center gap-3 mb-2">
                        <h1 class="text-3xl font-extrabold tracking-tight">{{ $activeDevice->device_name }}</h1>
                        <span
                            class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ $activeDevice->is_online ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30' : 'bg-red-500/20 text-red-300 border-red-500/30' }} border w-fit mx-auto md:mx-0 shadow-sm">
                            <span
                                class="w-2 h-2 {{ $activeDevice->is_online ? 'bg-emerald-400 animate-pulse' : 'bg-red-400' }} rounded-full mr-2"></span>
                            {{ $activeDevice->is_online ? 'Sistem Aktif' : 'Sistem Offline' }}
                        </span>
                    </div>
                    <p class="text-white/70 max-w-2xl leading-relaxed mt-3">
                        <i class="fa-solid fa-location-dot mr-1 text-emerald-400"></i> Location:
                        {{ $activeDevice->location ?? 'Not specified' }} <br>

                    </p>
                    <div
                        class="mt-5 text-sm text-white/40 italic flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 items-center justify-center md:justify-start">
                        <span id="last-update" class="bg-black/20 px-3 py-1 rounded-full border border-white/5">Memuat
                            waktu...</span>
                        @if($latestLog)
                            <span id="data-age"
                                class="bg-black/20 px-3 py-1 rounded-full border border-white/5 flex items-center">
                                <i class="fa-solid fa-clock mr-1 text-emerald-400"></i>
                                Last Data: {{ $latestLog->created_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                </div>
            </header>

            <!-- Grid Sensor -->
            <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 sm:gap-4">
                <!-- pH Tanah -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-blue-500 shadow-lg shadow-blue-500/30 z-10"><i class="fa-solid fa-flask"></i></div>
                    <h3 class="text-[10px] font-medium text-white/70 uppercase tracking-widest z-10">pH Tanah</h3>
                    <div class="flex items-baseline mt-1 z-10"><span id="val_ph"
                            class="text-xl font-bold">{{ $latestLog->ph ?? '--' }}</span><span
                            class="text-xs ml-1 text-white/60">pH</span></div>
                    <svg class="w-12 h-12 mt-3 transform -rotate-90 z-10">
                        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.05)" stroke-width="4"
                            fill="transparent" />
                        <circle id="ring_ph" cx="24" cy="24" r="20" stroke="#3b82f6" stroke-width="4" fill="transparent"
                            stroke-dasharray="125.6"
                            stroke-dashoffset="{{ $latestLog && $latestLog->ph ? 125.6 - (($latestLog->ph / 14) * 125.6) : 125.6 }}"
                            class="progress-ring" />
                    </svg>
                </div>
                <!-- Kelembaban Tanah -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-cyan-500/10 rounded-full blur-xl group-hover:bg-cyan-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-cyan-500 shadow-lg shadow-cyan-500/30 z-10"><i class="fa-solid fa-droplet"></i></div>
                    <h3 class="text-[10px] font-medium text-white/70 uppercase tracking-widest z-10">Lembab Tanah</h3>
                    <div class="flex items-baseline mt-1 z-10"><span id="val_soil"
                            class="text-xl font-bold">{{ $latestLog->soil_moisture ?? '--' }}</span><span
                            class="text-xs ml-1 text-white/60">%</span></div>
                    <svg class="w-12 h-12 mt-3 transform -rotate-90 z-10">
                        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.05)" stroke-width="4"
                            fill="transparent" />
                        <circle id="ring_soil" cx="24" cy="24" r="20" stroke="#06b6d4" stroke-width="4" fill="transparent"
                            stroke-dasharray="125.6"
                            stroke-dashoffset="{{ $latestLog && $latestLog->soil_moisture ? 125.6 - (($latestLog->soil_moisture / 100) * 125.6) : 125.6 }}"
                            class="progress-ring" />
                    </svg>
                </div>
                <!-- Cahaya -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-amber-500/10 rounded-full blur-xl group-hover:bg-amber-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-amber-500 shadow-lg shadow-amber-500/30 z-10"><i class="fa-solid fa-sun"></i></div>
                    <h3 class="text-[10px] font-medium text-white/70 uppercase tracking-widest z-10">Cahaya</h3>
                    <div class="flex items-baseline mt-1 z-10"><span id="val_light"
                            class="text-xl font-bold">{{ $latestLog->light_intensity ?? '--' }}</span><span
                            class="text-xs ml-1 text-white/60">lux</span></div>
                    <svg class="w-12 h-12 mt-3 transform -rotate-90 z-10">
                        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.05)" stroke-width="4"
                            fill="transparent" />
                        <circle id="ring_light" cx="24" cy="24" r="20" stroke="#f59e0b" stroke-width="4" fill="transparent"
                            stroke-dasharray="125.6"
                            stroke-dashoffset="{{ $latestLog && $latestLog->light_intensity ? 125.6 - (($latestLog->light_intensity / 2000) * 125.6) : 125.6 }}"
                            class="progress-ring" />
                    </svg>
                </div>
                <!-- Suhu Udara -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-orange-500/10 rounded-full blur-xl group-hover:bg-orange-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-orange-500 shadow-lg shadow-orange-500/30 z-10"><i class="fa-solid fa-temperature-half"></i></div>
                    <h3 class="text-[10px] font-medium text-white/70 uppercase tracking-widest z-10">Suhu Udara</h3>
                    <div class="flex items-baseline mt-1 z-10"><span id="val_temp"
                            class="text-xl font-bold">{{ $latestLog->temperature ?? '--' }}</span><span
                            class="text-xs ml-1 text-white/60">°C</span></div>
                    <svg class="w-12 h-12 mt-3 transform -rotate-90 z-10">
                        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.05)" stroke-width="4"
                            fill="transparent" />
                        <circle id="ring_temp" cx="24" cy="24" r="20" stroke="#f97316" stroke-width="4" fill="transparent"
                            stroke-dasharray="125.6"
                            stroke-dashoffset="{{ $latestLog && $latestLog->temperature ? (125.6 - (($latestLog->temperature / 50) * 125.6)) : 125.6 }}"
                            class="progress-ring" />
                    </svg>
                </div>
                <!-- Kelembaban Udara -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-indigo-500/10 rounded-full blur-xl group-hover:bg-indigo-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-indigo-500 shadow-lg shadow-indigo-500/30 z-10"><i class="fa-solid fa-wind"></i></div>
                    <h3 class="text-[10px] font-medium text-white/70 uppercase tracking-widest z-10">Lembab Udara</h3>
                    <div class="flex items-baseline mt-1 z-10"><span id="val_hum"
                            class="text-xl font-bold">{{ $latestLog->humidity ?? '--' }}</span><span
                            class="text-xs ml-1 text-white/60">%</span></div>
                    <svg class="w-12 h-12 mt-3 transform -rotate-90 z-10">
                        <circle cx="24" cy="24" r="20" stroke="rgba(255,255,255,0.05)" stroke-width="4"
                            fill="transparent" />
                        <circle id="ring_hum" cx="24" cy="24" r="20" stroke="#6366f1" stroke-width="4" fill="transparent"
                            stroke-dasharray="125.6"
                            stroke-dashoffset="{{ $latestLog && $latestLog->humidity ? 125.6 - (($latestLog->humidity / 100) * 125.6) : 125.6 }}"
                            class="progress-ring" />
                    </svg>
                </div>

            </div>

            <!-- Baris Bawah: Grafik & Kontrol -->
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 sm:gap-6">

                <!-- Grafik Pemantauan -->
                <div class="lg:col-span-3 glass rounded-3xl p-6 min-h-[400px]">
                    <div class="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i class="fa-solid fa-chart-line text-emerald-400"></i>
                            Tren Lingkungan <span class="text-sm font-normal text-white/50">(24 Data Terakhir)</span>
                        </h2>
                    </div>
                    <div class="h-[300px] w-full relative">
                        @if(count($recentLogs) > 0)
                            <canvas id="envChart"></canvas>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-white/50">
                                <i class="fa-solid fa-chart-bar text-5xl mb-2 opacity-50"></i>
                                Belum ada data terekam untuk perangkat ini.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Panel Kontrol Pompa -->
                <div class="space-y-4">
                    <!-- Saklar Pompa -->
                    <div
                        class="glass rounded-3xl p-6 flex flex-col items-center justify-between h-[180px] hover:bg-white/5 transition-colors">
                        <h2 class="text-sm font-bold tracking-wider uppercase text-white/70">POMPA AIR</h2>
                        <button id="pumpBtn"
                            class="relative inline-flex h-16 w-28 items-center rounded-full transition-all duration-300 bg-white/10 border border-white/20 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-transparent">
                            <span id="pumpHandle"
                                class="inline-block h-12 w-12 translate-x-2 transform rounded-full bg-white shadow-lg transition duration-300 flex items-center justify-center">
                                <i class="fa-solid fa-power-off text-gray-400 transition-colors"></i>
                            </span>
                        </button>
                        <span id="pumpStatusLabel"
                            class="text-sm font-bold tracking-widest text-white/40 bg-black/30 px-4 py-1 rounded-full">MATI</span>
                    </div>

                    <!-- Indikator Status Pompa -->
                    <div id="pumpIndicatorBox"
                        class="glass rounded-3xl p-6 flex flex-col items-center justify-center gap-4 h-[195px] transition-all duration-500 overflow-hidden relative">
                        <!-- Ripple Effects when Active -->
                        <div class="absolute inset-0 bg-blue-500/0 transition-colors duration-500" id="pumpBgGlow"></div>

                        <h2 class="text-[10px] font-bold text-white/70 uppercase relative z-10">Status Pompa</h2>
                        <div id="pumpVisual"
                            class="relative w-24 h-24 rounded-full flex items-center justify-center bg-white/5 transition-all duration-500 z-10">
                            <div class="w-20 h-20 rounded-full border-4 border-dashed border-white/10 opacity-30 flex items-center justify-center"
                                id="pumpSpin">
                                <i class="fa-solid fa-droplet text-white/20 text-2xl transition-colors"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Fuzzy Logic Status Card -->
                    <div id="fuzzyStatusCard" class="glass rounded-3xl p-5 transition-all duration-500">
                        <h2 class="text-[10px] font-bold text-white/70 uppercase tracking-widest mb-3 flex items-center gap-2">
                            <i class="fa-solid fa-brain text-purple-400"></i> Fuzzy Logic
                        </h2>
                        <div class="space-y-2">
                            <div class="flex justify-between items-center">
                                <span class="text-xs text-white/50">Output</span>
                                <span id="fuzzyOutputVal" class="text-sm font-bold text-purple-300">--</span>
                            </div>
                            <div class="w-full bg-white/10 rounded-full h-2 overflow-hidden">
                                <div id="fuzzyOutputBar" class="h-2 rounded-full bg-gradient-to-r from-purple-500 to-pink-500 transition-all duration-700" style="width: 0%"></div>
                            </div>
                            <div class="flex justify-between items-center mt-1">
                                <span class="text-xs text-white/50">Rekomendasi</span>
                                <span id="fuzzyPumpRec" class="text-xs font-bold px-2 py-0.5 rounded-full bg-white/10">--</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Scripts for Icons and Chart -->
        <script>
            // ══════════════════════════════════════════════════════════
            //  SENA Dashboard — Realtime HTTP Polling Engine
            // ══════════════════════════════════════════════════════════

            // ─── Config ─────────────────────────────────────────────
            const POLL_INTERVAL = 10000;  // Poll setiap 10 detik
            const activeDeviceId = {{ $activeDevice ? $activeDevice->id : 'null' }};
            const DASHBOARD_DATA_URL = '{{ route("dashboard.data") }}';

            // ─── Clock Update ───────────────────────────────────────
            function updateTime() {
                const now = new Date();
                const el = document.getElementById('last-update');
                if (el) {
                    el.innerHTML = '<i class="fa-solid fa-circle-dot fa-xs mr-1 text-emerald-400 animate-pulse"></i> Live: ' + now.toLocaleTimeString('id-ID');
                }
            }
            setInterval(updateTime, 1000);
            updateTime();

            // ─── Pump Control ───────────────────────────────────────
            const pumpBtn = document.getElementById('pumpBtn');
            const pumpHandle = document.getElementById('pumpHandle');
            const pumpStatusLabel = document.getElementById('pumpStatusLabel');
            const pumpIndicatorBox = document.getElementById('pumpIndicatorBox');
            const pumpVisual = document.getElementById('pumpVisual');
            const pumpSpin = document.getElementById('pumpSpin');
            const pumpBgGlow = document.getElementById('pumpBgGlow');

            let isPumpOn = {{ $activeDevice && $activeDevice->pump_state ? 'true' : 'false' }};

            function setPumpUI(on) {
                isPumpOn = on;
                const iconHandle = pumpHandle.querySelector('i');
                const iconSpin = pumpSpin.querySelector('i');

                if (on) {
                    pumpBtn.classList.add('bg-emerald-500', 'border-emerald-400');
                    pumpBtn.classList.remove('bg-white/10', 'border-white/20');
                    pumpHandle.classList.add('translate-x-14');
                    if (iconHandle) { iconHandle.classList.remove('text-gray-400'); iconHandle.classList.add('text-emerald-500'); }

                    pumpStatusLabel.textContent = 'HIDUP';
                    pumpStatusLabel.className = 'text-sm font-bold tracking-widest text-emerald-300 bg-emerald-500/20 px-4 py-1 rounded-full border border-emerald-500/30';

                    pumpIndicatorBox.classList.add('pump-active', 'border-blue-500/30');
                    pumpBgGlow.className = 'absolute inset-0 bg-blue-500/10 transition-colors duration-500';
                    pumpVisual.classList.remove('bg-white/5'); pumpVisual.classList.add('bg-blue-500/20');

                    pumpSpin.classList.add('animate-spin-slow', 'border-blue-400', 'opacity-80');
                    pumpSpin.classList.remove('opacity-30', 'border-white/10');
                    if (iconSpin) { iconSpin.classList.remove('text-white/20'); iconSpin.classList.add('text-blue-300'); }
                } else {
                    pumpBtn.classList.remove('bg-emerald-500', 'border-emerald-400');
                    pumpBtn.classList.add('bg-white/10', 'border-white/20');
                    pumpHandle.classList.remove('translate-x-14');
                    if (iconHandle) { iconHandle.classList.remove('text-emerald-500'); iconHandle.classList.add('text-gray-400'); }

                    pumpStatusLabel.textContent = 'MATI';
                    pumpStatusLabel.className = 'text-sm font-bold tracking-widest text-white/40 bg-black/30 px-4 py-1 rounded-full';

                    pumpIndicatorBox.classList.remove('pump-active', 'border-blue-500/30');
                    pumpBgGlow.className = 'absolute inset-0 bg-blue-500/0 transition-colors duration-500';
                    pumpVisual.classList.remove('bg-blue-500/20'); pumpVisual.classList.add('bg-white/5');

                    pumpSpin.classList.remove('animate-spin-slow', 'border-blue-400', 'opacity-80');
                    pumpSpin.classList.add('opacity-30', 'border-white/10');
                    if (iconSpin) { iconSpin.classList.remove('text-blue-300'); iconSpin.classList.add('text-white/20'); }
                }
            }

            // Set initial pump UI from server state
            setPumpUI(isPumpOn);

            pumpBtn.addEventListener('click', () => {
                if (!activeDeviceId) return;
                isPumpOn = !isPumpOn;
                setPumpUI(isPumpOn);

                fetch(`/devices/${activeDeviceId}/toggle-pump`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({ pump_state: isPumpOn })
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        console.error('Failed to change pump state', data);
                        isPumpOn = !isPumpOn;
                        setPumpUI(isPumpOn);
                    }
                })
                .catch(err => {
                    console.error('Network Error:', err);
                    isPumpOn = !isPumpOn;
                    setPumpUI(isPumpOn);
                });
            });

            // ─── Helper: Update progress ring ───────────────────────
            function updateRing(id, value, maxVal) {
                const ring = document.getElementById(id);
                if (ring && value !== null && value !== undefined) {
                    const circumference = 125.6;
                    ring.style.strokeDashoffset = circumference - ((value / maxVal) * circumference);
                }
            }

            // ─── Helper: Update sensor value with animation ─────────
            function updateSensorValue(id, value, suffix) {
                const el = document.getElementById(id);
                if (el) {
                    const newVal = (value !== null && value !== undefined) ? value : '--';
                    if (el.innerText !== String(newVal)) {
                        el.style.transition = 'opacity 0.3s';
                        el.style.opacity = '0.3';
                        setTimeout(() => {
                            el.innerText = newVal;
                            el.style.opacity = '1';
                        }, 150);
                    }
                }
            }

            // ─── Helper: Update fuzzy status card ───────────────────
            function updateFuzzyCard(fuzzyEval) {
                const outputEl = document.getElementById('fuzzyOutputVal');
                const barEl = document.getElementById('fuzzyOutputBar');
                const recEl = document.getElementById('fuzzyPumpRec');

                if (!fuzzyEval || fuzzyEval.crisp_output === undefined) {
                    if (outputEl) outputEl.innerText = '--';
                    if (barEl) barEl.style.width = '0%';
                    if (recEl) { recEl.innerText = '--'; recEl.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-white/10'; }
                    return;
                }

                const crisp = fuzzyEval.crisp_output;
                const pumpOn = fuzzyEval.pump_decision;

                if (outputEl) outputEl.innerText = crisp.toFixed(3);
                if (barEl) barEl.style.width = (crisp * 100) + '%';
                if (recEl) {
                    if (pumpOn) {
                        recEl.innerText = 'SIRAM';
                        recEl.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                    } else {
                        recEl.innerText = 'TIDAK';
                        recEl.className = 'text-xs font-bold px-2 py-0.5 rounded-full bg-red-500/20 text-red-300 border border-red-500/30';
                    }
                }
            }

            @if(count($recentLogs) > 0)
            // ─── Chart.js Initialization ────────────────────────────
            const ctx = document.getElementById('envChart').getContext('2d');

            const gradientSuhu = ctx.createLinearGradient(0, 0, 0, 300);
            gradientSuhu.addColorStop(0, 'rgba(16, 185, 129, 0.5)');
            gradientSuhu.addColorStop(1, 'rgba(16, 185, 129, 0)');

            const gradientLembab = ctx.createLinearGradient(0, 0, 0, 300);
            gradientLembab.addColorStop(0, 'rgba(59, 130, 246, 0.5)');
            gradientLembab.addColorStop(1, 'rgba(59, 130, 246, 0)');

            const chartLabels = {!! json_encode($recentLogs->pluck('created_at')->map(fn($date) => $date->format('H:i'))) !!};
            const chartDataSuhu = {!! json_encode($recentLogs->pluck('temperature')) !!};
            const chartDataLembab = {!! json_encode($recentLogs->pluck('humidity')) !!};
            const chartDataSoil = {!! json_encode($recentLogs->pluck('soil_moisture')) !!};
            const chartDataPh = {!! json_encode($recentLogs->pluck('ph')) !!};
            const chartDataLight = {!! json_encode($recentLogs->pluck('light_intensity')) !!};

            Chart.defaults.color = 'rgba(255, 255, 255, 0.7)';
            Chart.defaults.font.family = "'Plus Jakarta Sans', sans-serif";

            window.envChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: chartLabels,
                    datasets: [
                        {
                            label: 'Suhu Udara (°C)',
                            data: chartDataSuhu,
                            borderColor: '#10b981',
                            borderWidth: 3,
                            backgroundColor: gradientSuhu,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#10b981',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Lembab Udara (%)',
                            data: chartDataLembab,
                            borderColor: '#3b82f6',
                            borderWidth: 3,
                            backgroundColor: gradientLembab,
                            fill: true,
                            tension: 0.4,
                            pointBackgroundColor: '#3b82f6',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6
                        },
                        {
                            label: 'Lembab Tanah (%)',
                            data: chartDataSoil,
                            borderColor: '#06b6d4',
                            borderWidth: 3,
                            backgroundColor: 'transparent',
                            fill: false,
                            borderDash: [5, 5],
                            tension: 0.4,
                            pointBackgroundColor: '#06b6d4',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'pH Tanah',
                            data: chartDataPh,
                            borderColor: '#8b5cf6',
                            borderWidth: 3,
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#8b5cf6',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'y'
                        },
                        {
                            label: 'Cahaya (Lux)',
                            data: chartDataLight,
                            borderColor: '#f59e0b',
                            borderWidth: 3,
                            backgroundColor: 'transparent',
                            fill: false,
                            tension: 0.4,
                            pointBackgroundColor: '#f59e0b',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 4,
                            pointHoverRadius: 6,
                            yAxisID: 'yLight'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                            labels: { usePointStyle: true, padding: 20, font: { size: 12, weight: '600' } }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(15, 23, 42, 0.8)',
                            titleColor: '#fff',
                            bodyColor: '#cbd5e1',
                            borderColor: 'rgba(255,255,255,0.1)',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            bodyFont: { size: 13 },
                            titleFont: { size: 14, weight: 'bold' }
                        }
                    },
                    scales: {
                        y: {
                            type: 'linear', display: true, position: 'left',
                            grid: { color: 'rgba(255,255,255,0.05)', drawBorder: false },
                            ticks: { font: { size: 11 } },
                            border: { display: false }
                        },
                        yLight: {
                            type: 'linear', display: true, position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: { font: { size: 11 }, color: '#f59e0b' },
                            border: { display: false }
                        },
                        x: {
                            grid: { display: false, drawBorder: false },
                            ticks: { maxTicksLimit: 8, font: { size: 11 } },
                            border: { display: false }
                        }
                    }
                }
            });
            @endif

            // ─── Update initial fuzzy card from server data ─────────
            @if(isset($fuzzyResult) && $fuzzyResult)
                updateFuzzyCard({!! json_encode($fuzzyResult) !!});
            @endif

            // ══════════════════════════════════════════════════════════
            //  REALTIME POLLING ENGINE
            //  Fetches /dashboard/data every 10 seconds and updates
            //  all sensor cards, progress rings, chart, pump status,
            //  fuzzy evaluation, and timestamps automatically.
            // ══════════════════════════════════════════════════════════
            let pollFailCount = 0;

            async function pollDashboardData() {
                if (!activeDeviceId) return;

                try {
                    const url = `${DASHBOARD_DATA_URL}?device_id=${activeDeviceId}&_=${Date.now()}`;
                    const response = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}`);
                    }

                    const data = await response.json();
                    pollFailCount = 0;  // Reset on success

                    if (!data.success) return;

                    // ── Update Sensor Values ────────────────────────
                    if (data.latest) {
                        const L = data.latest;
                        updateSensorValue('val_ph', L.ph, 'pH');
                        updateSensorValue('val_soil', L.soil_moisture, '%');
                        updateSensorValue('val_light', L.light_intensity, 'lux');
                        updateSensorValue('val_temp', L.temperature, '°C');
                        updateSensorValue('val_hum', L.humidity, '%');

                        // Update Progress Rings
                        updateRing('ring_ph', L.ph, 14);
                        updateRing('ring_soil', L.soil_moisture, 100);
                        updateRing('ring_light', L.light_intensity, 2000);
                        updateRing('ring_temp', L.temperature, 50);
                        updateRing('ring_hum', L.humidity, 100);

                        // Update "Last Data" age
                        const ageEl = document.getElementById('data-age');
                        if (ageEl && L.time_ago) {
                            ageEl.innerHTML = `<i class="fa-solid fa-clock mr-1 text-emerald-400"></i> Last Data: ${L.time_ago}`;
                        }
                    }

                    // ── Update Fuzzy Evaluation ─────────────────────
                    if (data.fuzzy_evaluation) {
                        updateFuzzyCard(data.fuzzy_evaluation);
                    }

                    // ── Update Chart ────────────────────────────────
                    if (window.envChartInstance && data.chart) {
                        const chart = window.envChartInstance;
                        chart.data.labels = data.chart.labels || [];
                        chart.data.datasets[0].data = data.chart.temperature || [];
                        chart.data.datasets[1].data = data.chart.humidity || [];
                        chart.data.datasets[2].data = data.chart.soil_moisture || [];
                        chart.data.datasets[3].data = data.chart.ph || [];
                        chart.data.datasets[4].data = data.chart.light_intensity || [];
                        chart.update('none');  // 'none' = no animation for smooth update
                    }

                    console.log(`[Poll] Data updated @ ${new Date().toLocaleTimeString()}`);

                } catch (err) {
                    pollFailCount++;
                    console.warn(`[Poll] Error (attempt ${pollFailCount}):`, err.message);
                }
            }

            // Initial poll on page load + start interval
            pollDashboardData();
            setInterval(pollDashboardData, POLL_INTERVAL);
        </script>
    @endif
</body>

</html>