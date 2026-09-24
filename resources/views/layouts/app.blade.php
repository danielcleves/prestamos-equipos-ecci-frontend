<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Préstamo de Equipos ECCI' }}</title>

    {{-- CDN oficial de Tailwind CSS para aplicar los estilos de inmediato --}}
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ecci: {
                            sidebar: '#0B2559',
                            footer: '#081d45',
                        }
                    }
                }
            }
        }
    </script>

    {{-- Ocultar modales hasta que Alpine cargue --}}
    <style>
        [x-cloak] { display: none !important; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif; }
    </style>

    {{-- Alpine.js para filtros y modales --}}
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="h-full flex overflow-hidden text-slate-800 antialiased">

    {{-- Sidebar lateral izquierdo (Exacto al Mockup) --}}
    <aside class="w-64 bg-[#0B2559] text-white flex flex-col justify-between shrink-0 h-screen z-20 select-none">
        <div class="flex flex-col flex-1 overflow-y-auto">
            
            {{-- Header Sidebar --}}
            <div class="h-16 flex flex-col justify-center px-6 border-b border-blue-900/60 shrink-0">
                <span class="text-sm font-bold tracking-tight text-white leading-tight">Sistema de Préstamo</span>
                <span class="text-[11px] text-blue-300 font-medium">de Equipos ECCI</span>
            </div>

            {{-- Navegación --}}
            <div class="px-4 py-5 flex-1 space-y-6">
                <div>
                    <span class="px-3 text-[10px] font-bold uppercase tracking-wider text-blue-300/70">Menú Principal</span>
                    <nav class="mt-2 space-y-1 text-xs font-medium">
                        
                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Inicio</span>
                        </a>

                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            <span>Equipos</span>
                        </a>

                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                            <span>Préstamos</span>
                        </a>

                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Solicitudes</span>
                        </a>

                        {{-- Item Activo Usuarios --}}
                        <a href="{{ route('usuarios.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-blue-600 text-white font-semibold shadow-sm">
                            <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                            <span>Usuarios</span>
                        </a>

                        <a href="#" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                            <span>Reportes</span>
                        </a>

                    </nav>
                </div>
            </div>
        </div>

        {{-- Footer Sidebar: Perfil de Administrador --}}
        <div class="p-4 border-t border-blue-900/60 flex items-center justify-between bg-[#081d45] shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-full bg-blue-500/40 border border-blue-400/40 flex items-center justify-center font-bold text-xs text-white shrink-0">
                    {{ strtoupper(substr(session('user')['name'] ?? 'AD', 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ session('user')['name'] ?? 'Administrador' }}</p>
                    <p class="text-[10px] text-blue-300 truncate">{{ session('user')['email'] ?? 'admin@ecci.edu.co' }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" class="shrink-0 ml-1">
                @csrf
                <button type="submit" title="Cerrar sesión" class="p-1.5 text-blue-300 hover:text-rose-300 hover:bg-white/5 rounded-lg transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </button>
            </form>
        </div>
    </aside>

    {{-- Área Principal de Trabajo --}}
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden bg-[#F8FAFC]">
        
        {{-- Barra Superior (Topbar del Mockup con Campana y Avatar) --}}
        <header class="h-14 bg-white border-b border-slate-200/80 px-8 flex justify-between items-center shrink-0 z-10">
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span>Sistema</span>
                <span>&gt;</span>
                <span class="text-slate-700 font-medium">Gestión de usuarios</span>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </button>
                <div class="w-7 h-7 rounded-full bg-[#0B2559] text-white flex items-center justify-center font-bold text-[10px]">
                    {{ strtoupper(substr(session('user')['name'] ?? 'AD', 0, 2)) }}
                </div>
            </div>
        </header>

        {{-- Contenedor con Scroll --}}
        <main class="flex-1 overflow-y-auto px-8 py-6">
            <div class="max-w-[1400px] mx-auto w-full">
                @yield('content')
            </div>
        </main>
    </div>

</body>
</html>