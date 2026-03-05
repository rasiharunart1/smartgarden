<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Login</title>

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
            display: flex;
            align-items: center;
            justify-content: center;
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
<body class="text-white p-4">

    <div class="w-full max-w-md">
        <!-- Logo Area -->
        <div class="flex justify-center mb-8">
            <div class="w-20 h-20 bg-gradient-to-tr from-emerald-400 to-teal-500 rounded-2xl flex items-center justify-center shadow-[0_0_30px_rgba(16,185,129,0.4)] border border-white/30">
                <i data-lucide="sprout" class="w-10 h-10 text-white drop-shadow-md"></i>
            </div>
        </div>
        
        <div class="text-center mb-8">
            <h1 class="text-2xl font-bold tracking-tight mb-1">Smart Farming Platform</h1>
            <p class="text-white/60 text-sm">IoT Telemetry & Control Dashboard</p>
        </div>

        <!-- The Content Slot (Login/Register Forms) -->
        <div class="glass rounded-3xl p-8 shadow-2xl">
            {{ $slot }}
        </div>
        
        <div class="text-center mt-8 text-xs text-white/40">
            &copy; {{ date('Y') }} SmartFarming Alpha. All rights reserved.
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
