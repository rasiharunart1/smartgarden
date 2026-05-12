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
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- MQTT.js for Realtime WebSockets -->
    <script src="https://unpkg.com/mqtt/dist/mqtt.min.js"></script>

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

        <div class="flex items-center space-x-3">
            @if($devices->count() > 0)
                <form action="{{ route('dashboard') }}" method="GET" class="flex items-center space-x-2">
                    <select name="device_id" onchange="this.form.submit()"
                        class="glass rounded-lg text-white bg-transparent border-white/30 px-4 py-2 text-sm focus:ring-emerald-500 appearance-none">
                        @foreach($devices as $dev)
                            <option class="text-black" value="{{ $dev->id }}" {{ ($activeDevice && $activeDevice->id == $dev->id) ? 'selected' : '' }}>
                                {{ $dev->device_name }}
                            </option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a href="{{ route('devices.index') }}"
                class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="hard-drive" class="w-4 h-4 mr-1"></i> Devices
            </a>
            <a href="{{ route('logs.index') }}"
                class="text-white/70 hover:text-emerald-400 transition text-sm flex items-center px-2">
                <i data-lucide="history" class="w-4 h-4 mr-1"></i> Logs
            </a>
        </div>
    </div>

    @if(!$activeDevice)
        <div class="max-w-6xl mx-auto glass rounded-3xl p-12 text-center mt-12">
            <i data-lucide="alert-circle" class="w-20 h-20 mx-auto mb-6 text-amber-400"></i>
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
            <header class="glass rounded-3xl p-6 md:p-8 flex flex-col md:flex-row gap-8 items-center cursor-default">
                <div
                    class="w-32 h-32 md:w-40 md:h-40 bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-lg border border-white/30 relative overflow-hidden group">
                    <div
                        class="absolute inset-0 bg-white/20 transform -translate-x-full group-hover:translate-x-full transition-transform duration-1000">
                    </div>
                    <i data-lucide="sprout"
                        class="w-16 h-16 text-white drop-shadow-md transform group-hover:scale-110 transition-transform"></i>
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
                        <i data-lucide="map-pin" class="inline w-4 h-4 mr-1 text-emerald-400"></i> Location:
                        {{ $activeDevice->location ?? 'Not specified' }} <br>

                    </p>
                    <div
                        class="mt-5 text-sm text-white/40 italic flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4 items-center justify-center md:justify-start">
                        <span id="last-update" class="bg-black/20 px-3 py-1 rounded-full border border-white/5">Memuat
                            waktu...</span>
                        @if($latestLog)
                            <span id="data-age"
                                class="bg-black/20 px-3 py-1 rounded-full border border-white/5 flex items-center">
                                <i data-lucide="clock" class="w-3 h-3 mr-1 text-emerald-400"></i>
                                Last Data: {{ $latestLog->created_at->diffForHumans() }}
                            </span>
                        @endif
                    </div>
                </div>
            </header>

            <!-- Grid Sensor -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4">
                <!-- pH Tanah -->
                <div
                    class="glass p-4 rounded-2xl flex flex-col items-center justify-center transition-all hover:bg-white/20 hover:scale-105 cursor-default relative overflow-hidden group">
                    <div
                        class="absolute -right-8 -top-8 w-24 h-24 bg-blue-500/10 rounded-full blur-xl group-hover:bg-blue-500/20 transition-all">
                    </div>
                    <div class="p-2 rounded-full mb-2 bg-blue-500 shadow-lg shadow-blue-500/30 z-10"><i
                            data-lucide="activity" class="w-5 h-5"></i></div>
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
                    <div class="p-2 rounded-full mb-2 bg-cyan-500 shadow-lg shadow-cyan-500/30 z-10"><i
                            data-lucide="droplets" class="w-5 h-5"></i></div>
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
                    <div class="p-2 rounded-full mb-2 bg-amber-500 shadow-lg shadow-amber-500/30 z-10"><i data-lucide="sun"
                            class="w-5 h-5"></i></div>
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
                    <div class="p-2 rounded-full mb-2 bg-orange-500 shadow-lg shadow-orange-500/30 z-10"><i
                            data-lucide="thermometer" class="w-5 h-5"></i></div>
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
                    <div class="p-2 rounded-full mb-2 bg-indigo-500 shadow-lg shadow-indigo-500/30 z-10"><i
                            data-lucide="waves" class="w-5 h-5"></i></div>
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
            <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">

                <!-- Grafik Pemantauan -->
                <div class="lg:col-span-3 glass rounded-3xl p-6 min-h-[400px]">
                    <div class="flex items-center justify-between mb-6 border-b border-white/10 pb-4">
                        <h2 class="text-xl font-bold flex items-center gap-2">
                            <i data-lucide="activity" class="text-emerald-400"></i>
                            Tren Lingkungan <span class="text-sm font-normal text-white/50">(24 Data Terakhir)</span>
                        </h2>
                    </div>
                    <div class="h-[300px] w-full relative">
                        @if(count($recentLogs) > 0)
                            <canvas id="envChart"></canvas>
                        @else
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-white/50">
                                <i data-lucide="bar-chart-2" class="w-12 h-12 mb-2 opacity-50"></i>
                                Belum ada data terekam untuk perangkat ini.
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Panel Kontrol Pompa -->
                <div class="space-y-6">
                    <!-- Saklar Pompa -->
                    <div
                        class="glass rounded-3xl p-6 flex flex-col items-center justify-between h-[180px] hover:bg-white/5 transition-colors">
                        <h2 class="text-sm font-bold tracking-wider uppercase text-white/70">POMPA AIR</h2>
                        <button id="pumpBtn"
                            class="relative inline-flex h-16 w-28 items-center rounded-full transition-all duration-300 bg-white/10 border border-white/20 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 focus:ring-offset-transparent">
                            <span id="pumpHandle"
                                class="inline-block h-12 w-12 translate-x-2 transform rounded-full bg-white shadow-lg transition duration-300 flex items-center justify-center">
                                <i data-lucide="power" class="text-gray-400 w-5 h-5 transition-colors"></i>
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
                                <i data-lucide="waves" class="text-white/20 w-8 h-8 transition-colors"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Scripts for Icons and Chart -->
        <script>
            // Inisialisasi Ikon Lucide
            lucide.createIcons();

            // Update Waktu
            function updateTime() {
                const now = new Date();
                document.getElementById('last-update').innerHTML = '<i data-lucide="monitor" class="w-3 h-3 inline mr-1 text-emerald-400"></i> Live: ' + now.toLocaleTimeString('id-ID');
                // Re-init lucide for the dynamically added icon
                lucide.createIcons({
                    root: document.getElementById('last-update')
                });
            }
            setInterval(updateTime, 1000);
            updateTime();

            // Kontrol Pompa (Visual & Simulasi)
            const pumpBtn = document.getElementById('pumpBtn');
            const pumpHandle = document.getElementById('pumpHandle');
            const pumpStatusLabel = document.getElementById('pumpStatusLabel');
            const pumpIndicatorBox = document.getElementById('pumpIndicatorBox');
            const pumpVisual = document.getElementById('pumpVisual');
            const pumpSpin = document.getElementById('pumpSpin');
            const pumpBgGlow = document.getElementById('pumpBgGlow');

            // Initialize from database state
            let isPumpOn = {{ $activeDevice && $activeDevice->pump_state ? 'true' : 'false' }};
            const activeDeviceId = {{ $activeDevice ? $activeDevice->id : 'null' }};

            // Set Initial UI visually
            if (isPumpOn) {
                pumpBtn.classList.add('bg-emerald-500', 'border-emerald-400');
                pumpBtn.classList.remove('bg-white/10', 'border-white/20');
                pumpHandle.classList.add('translate-x-14');
                let pIcon = pumpHandle.querySelector('svg') || pumpHandle.querySelector('i');
                if (pIcon) pIcon.classList.replace('text-gray-400', 'text-emerald-500');

                pumpStatusLabel.textContent = 'HIDUP';
                pumpStatusLabel.classList.replace('text-white/40', 'text-emerald-300');
                pumpStatusLabel.classList.replace('bg-black/30', 'bg-emerald-500/20');
                pumpStatusLabel.classList.add('border', 'border-emerald-500/30');

                pumpIndicatorBox.classList.add('pump-active', 'border-blue-500/30');
                pumpBgGlow.classList.replace('bg-blue-500/0', 'bg-blue-500/10');

                pumpVisual.classList.replace('bg-white/5', 'bg-blue-500/20');

                pumpSpin.classList.add('animate-spin-slow', 'border-blue-400', 'opacity-80');
                pumpSpin.classList.remove('opacity-30', 'border-white/10');
                let sIcon = pumpSpin.querySelector('svg') || pumpSpin.querySelector('i');
                if (sIcon) sIcon.classList.replace('text-white/20', 'text-blue-300');
            }

            pumpBtn.addEventListener('click', () => {
                if (!activeDeviceId) return;

                // Toggle optimistic UI
                isPumpOn = !isPumpOn;

                if (isPumpOn) {
                    // UI Hidup
                    pumpBtn.classList.add('bg-emerald-500', 'border-emerald-400');
                    pumpBtn.classList.remove('bg-white/10', 'border-white/20');

                    pumpHandle.classList.add('translate-x-14');
                    let pIcon = pumpHandle.querySelector('svg');
                    if (pIcon) pIcon.classList.replace('text-gray-400', 'text-emerald-500');

                    pumpStatusLabel.textContent = 'HIDUP';
                    pumpStatusLabel.classList.replace('text-white/40', 'text-emerald-300');
                    pumpStatusLabel.classList.replace('bg-black/30', 'bg-emerald-500/20');
                    pumpStatusLabel.classList.add('border', 'border-emerald-500/30');

                    pumpIndicatorBox.classList.add('pump-active', 'border-blue-500/30');
                    pumpBgGlow.classList.replace('bg-blue-500/0', 'bg-blue-500/10');

                    pumpVisual.classList.replace('bg-white/5', 'bg-blue-500/20');

                    pumpSpin.classList.add('animate-spin-slow', 'border-blue-400', 'opacity-80');
                    pumpSpin.classList.remove('opacity-30', 'border-white/10');
                    let sIcon = pumpSpin.querySelector('svg');
                    if (sIcon) sIcon.classList.replace('text-white/20', 'text-blue-300');

                    // console.log('Perintah kirim ke IoT: ON');
                } else {
                    // UI Mati
                    pumpBtn.classList.remove('bg-emerald-500', 'border-emerald-400');
                    pumpBtn.classList.add('bg-white/10', 'border-white/20');

                    pumpHandle.classList.remove('translate-x-14');
                    let pIcon = pumpHandle.querySelector('svg');
                    if (pIcon) pIcon.classList.replace('text-emerald-500', 'text-gray-400');

                    pumpStatusLabel.textContent = 'MATI';
                    pumpStatusLabel.classList.replace('text-emerald-300', 'text-white/40');
                    pumpStatusLabel.classList.replace('bg-emerald-500/20', 'bg-black/30');
                    pumpStatusLabel.classList.remove('border', 'border-emerald-500/30');

                    pumpIndicatorBox.classList.remove('pump-active', 'border-blue-500/30');
                    pumpBgGlow.classList.replace('bg-blue-500/10', 'bg-blue-500/0');

                    pumpVisual.classList.replace('bg-blue-500/20', 'bg-white/5');

                    pumpSpin.classList.remove('animate-spin-slow', 'border-blue-400', 'opacity-80');
                    pumpSpin.classList.add('opacity-30', 'border-white/10');
                    let sIcon = pumpSpin.querySelector('svg');
                    if (sIcon) sIcon.classList.replace('text-blue-300', 'text-white/20');
                }

                // Push change to DB
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
                            // Optional: Revert UI if needed here
                        }
                    })
                    .catch(err => console.error('Network Error:', err));
            });

            @if(count($recentLogs) > 0)
                // Grafik Chart.js
                const ctx = document.getElementById('envChart').getContext('2d');

                // Gradient API
                const gradientSuhu = ctx.createLinearGradient(0, 0, 0, 300);
                gradientSuhu.addColorStop(0, 'rgba(16, 185, 129, 0.5)'); // Emerald 500
                gradientSuhu.addColorStop(1, 'rgba(16, 185, 129, 0)');

                const gradientLembab = ctx.createLinearGradient(0, 0, 0, 300);
                gradientLembab.addColorStop(0, 'rgba(59, 130, 246, 0.5)'); // Blue 500
                gradientLembab.addColorStop(1, 'rgba(59, 130, 246, 0)');

                // Data mapping from PHP
                const chartLabels = {!! json_encode($recentLogs->pluck('created_at')->map(fn($date) => $date->format('H:i'))) !!};
                const chartDataSuhu = {!! json_encode($recentLogs->pluck('temperature')) !!};
                const chartDataLembab = {!! json_encode($recentLogs->pluck('humidity')) !!};
                const chartDataSoil = {!! json_encode($recentLogs->pluck('soil_moisture')) !!};
                const chartDataPh = {!! json_encode($recentLogs->pluck('ph')) !!};
                const chartDataLight = {!! json_encode($recentLogs->pluck('light_intensity')) !!};

                // Add glassmorphism feel to Chart tooltip
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
                                borderColor: '#10b981', // Emerald 500
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
                                borderColor: '#3b82f6', // Blue 500
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
                                borderColor: '#06b6d4', // Cyan 500
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
                                borderColor: '#3b82f6', // Blue 500
                                borderWidth: 3,
                                backgroundColor: 'transparent',
                                fill: false,
                                tension: 0.4,
                                pointBackgroundColor: '#3b82f6',
                                pointBorderColor: '#fff',
                                pointBorderWidth: 2,
                                pointRadius: 4,
                                pointHoverRadius: 6,
                                yAxisID: 'y'
                            },
                            {
                                label: 'Cahaya (Lux)',
                                data: chartDataLight,
                                borderColor: '#f59e0b', // Amber 500
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
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            legend: {
                                display: true,
                                position: 'top',
                                labels: {
                                    usePointStyle: true,
                                    padding: 20,
                                    font: { size: 12, weight: '600' }
                                }
                            },
                            tooltip: {
                                backgroundColor: 'rgba(15, 23, 42, 0.8)', // Slate 900 with opacity
                                titleColor: '#fff',
                                bodyColor: '#cbd5e1', // Slate 300
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
                                type: 'linear',
                                display: true,
                                position: 'left',
                                grid: {
                                    color: 'rgba(255,255,255,0.05)',
                                    drawBorder: false
                                },
                                ticks: {
                                    font: { size: 11 }
                                },
                                border: { display: false }
                            },
                            yLight: {
                                type: 'linear',
                                display: true,
                                position: 'right',
                                grid: {
                                    drawOnChartArea: false, // only draw grid lines for one axis
                                },
                                ticks: {
                                    font: { size: 11 },
                                    color: '#f59e0b' // Amber color for visibility
                                },
                                border: { display: false }
                            },
                            x: {
                                grid: { display: false, drawBorder: false },
                                ticks: {
                                    maxTicksLimit: 8,
                                    font: { size: 11 }
                                },
                                border: { display: false }
                            }
                        }
                    }
                });

                // Hubungkan ke HiveMQ Broker via WebSockets untuk Telemetri Real-Time
                const mqttClient = mqtt.connect('wss://broker.hivemq.com:8884/mqtt', {
                    clientId: 'sena_web_' + Math.random().toString(16).substr(2, 8)
                });

                const activeDeviceCode = '{{ $activeDevice ? $activeDevice->device_code : "" }}';

                mqttClient.on('connect', function () {
                    console.log('Connected to MQTT via WebSockets');
                    if (activeDeviceCode) {
                        mqttClient.subscribe('sena/tx/' + activeDeviceCode, function (err) {
                            if (!err) {
                                console.log('Subscribed to real-time telemetry: sena/tx/' + activeDeviceCode);
                            }
                        });
                    }
                });

                mqttClient.on('message', function (topic, message) {
                    try {
                        const payload = JSON.parse(message.toString());
                        console.log('Live Telemetry:', payload);

                        // Update Sensors
                        if (payload.ph !== undefined) {
                            document.getElementById('val_ph').innerText = payload.ph;
                            document.getElementById('ring_ph').style.strokeDashoffset = 125.6 - ((payload.ph / 14) * 125.6);
                        }
                        if (payload.soil_moisture !== undefined) {
                            document.getElementById('val_soil').innerText = payload.soil_moisture;
                            document.getElementById('ring_soil').style.strokeDashoffset = 125.6 - ((payload.soil_moisture / 100) * 125.6);
                        }
                        if (payload.light_intensity !== undefined) {
                            document.getElementById('val_light').innerText = payload.light_intensity;
                            document.getElementById('ring_light').style.strokeDashoffset = 125.6 - ((payload.light_intensity / 2000) * 125.6);
                        }
                        if (payload.temperature !== undefined) {
                            document.getElementById('val_temp').innerText = payload.temperature;
                            document.getElementById('ring_temp').style.strokeDashoffset = 125.6 - ((payload.temperature / 50) * 125.6);
                        }
                        if (payload.humidity !== undefined) {
                            document.getElementById('val_hum').innerText = payload.humidity;
                            document.getElementById('ring_hum').style.strokeDashoffset = 125.6 - ((payload.humidity / 100) * 125.6);
                        }

                        // Sync Pump Status if supplied from Device
                        if (payload.pump_state !== undefined) {
                            const devicePumpOn = payload.pump_state == 1 || payload.pump_state === true;
                            if (devicePumpOn !== isPumpOn) {
                                isPumpOn = devicePumpOn;
                                if (isPumpOn) {
                                    // Turn ON UI
                                    pumpBtn.classList.add('bg-emerald-500', 'border-emerald-400');
                                    pumpBtn.classList.remove('bg-white/10', 'border-white/20');
                                    pumpHandle.classList.add('translate-x-14');
                                    let pIcon = pumpHandle.querySelector('svg');
                                    if(pIcon) pIcon.classList.replace('text-gray-400', 'text-emerald-500');
                                    pumpStatusLabel.textContent = 'HIDUP';
                                    pumpStatusLabel.classList.replace('text-white/40', 'text-emerald-300');
                                    pumpStatusLabel.classList.replace('bg-black/30', 'bg-emerald-500/20');
                                    pumpStatusLabel.classList.add('border', 'border-emerald-500/30');
                                    pumpIndicatorBox.classList.add('pump-active', 'border-blue-500/30');
                                    pumpBgGlow.classList.replace('bg-blue-500/0', 'bg-blue-500/10');
                                    pumpVisual.classList.replace('bg-white/5', 'bg-blue-500/20');
                                    pumpSpin.classList.add('animate-spin-slow', 'border-blue-400', 'opacity-80');
                                    pumpSpin.classList.remove('opacity-30', 'border-white/10');
                                    let sIcon = pumpSpin.querySelector('svg');
                                    if(sIcon) sIcon.classList.replace('text-white/20', 'text-blue-300');
                                } else {
                                    // Turn OFF UI
                                    pumpBtn.classList.remove('bg-emerald-500', 'border-emerald-400');
                                    pumpBtn.classList.add('bg-white/10', 'border-white/20');
                                    pumpHandle.classList.remove('translate-x-14');
                                    let pIcon = pumpHandle.querySelector('svg');
                                    if(pIcon) pIcon.classList.replace('text-emerald-500', 'text-gray-400');
                                    pumpStatusLabel.textContent = 'MATI';
                                    pumpStatusLabel.classList.replace('text-emerald-300', 'text-white/40');
                                    pumpStatusLabel.classList.replace('bg-emerald-500/20', 'bg-black/30');
                                    pumpStatusLabel.classList.remove('border', 'border-emerald-500/30');
                                    pumpIndicatorBox.classList.remove('pump-active', 'border-blue-500/30');
                                    pumpBgGlow.classList.replace('bg-blue-500/10', 'bg-blue-500/0');
                                    pumpVisual.classList.replace('bg-blue-500/20', 'bg-white/5');
                                    pumpSpin.classList.remove('animate-spin-slow', 'border-blue-400', 'opacity-80');
                                    pumpSpin.classList.add('opacity-30', 'border-white/10');
                                    let sIcon = pumpSpin.querySelector('svg');
                                    if(sIcon) sIcon.classList.replace('text-blue-300', 'text-white/20');
                                }
                            }
                        }

                        // Update Time age
                        const ageDisp = document.getElementById('data-age');
                        if (ageDisp) {
                            ageDisp.innerHTML = `<i data-lucide="clock" class="w-3 h-3 mr-1 text-emerald-400"></i> Last Data: Just now`;
                            lucide.createIcons({ root: ageDisp });
                        }

                        // Update Chart Instance realtime
                        if (window.envChartInstance && payload.temperature !== undefined) {
                            const timeStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                            window.envChartInstance.data.labels.push(timeStr);
                            window.envChartInstance.data.datasets[0].data.push(payload.temperature);
                            window.envChartInstance.data.datasets[1].data.push(payload.humidity);
                            window.envChartInstance.data.datasets[2].data.push(payload.soil_moisture);
                            window.envChartInstance.data.datasets[3].data.push(payload.ph);
                            window.envChartInstance.data.datasets[4].data.push(payload.light_intensity);
                            
                            // Keep max 24 dots
                            if (window.envChartInstance.data.labels.length > 24) {
                                window.envChartInstance.data.labels.shift();
                                window.envChartInstance.data.datasets.forEach(doc => doc.data.shift());
                            }
                            window.envChartInstance.update('none');
                        }
                    } catch (e) {
                        console.error('Error parsing MQTT message', e);
                    }
                });
            @endif
        </script>
    @endif
</body>

</html>