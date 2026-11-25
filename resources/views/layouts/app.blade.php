<!DOCTYPE html>
<html lang="es" class="h-full bg-[#F8FAFC]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Préstamo de Equipos ECCI' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com/3.4.16"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        ecci: {
                            sidebar: '#0B3D91',
                            blue: '#1D5FD0',
                            dark: '#082E6D',
                        }
                    }
                }
            }
        }
    </script>

    <style>
        [x-cloak] { display: none !important; }
        body { font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        /* H-26: halo de foco de 2 px en enlaces y campos (guía UI/UX §13). */
        a:focus-visible,
        button:focus-visible,
        input:focus-visible,
        select:focus-visible,
        textarea:focus-visible,
        [tabindex]:focus-visible {
            outline: 2px solid #0B3D91;
            outline-offset: 2px;
        }
    </style>

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
</head>
<body x-data="{ menuAbierto: false }" class="h-full flex overflow-hidden text-slate-800 antialiased">

    {{-- H-26: primer elemento enfocable; permite saltar el menú lateral con el teclado --}}
    <a href="#contenido-principal" class="sr-only focus:not-sr-only focus:absolute focus:z-[70] focus:top-2 focus:left-2 focus:px-4 focus:py-2 focus:bg-white focus:text-[#0B3D91] focus:text-xs focus:font-semibold focus:rounded-[10px] focus:shadow-md">Saltar al contenido</a>

    {{-- Overlay móvil --}}
    <div x-show="menuAbierto" @click="menuAbierto = false" x-cloak class="fixed inset-0 bg-slate-900/50 z-30 md:hidden"></div>

    {{-- Variables de sesión y estado de rol --}}
    @php
        $user = session('user') ?? [];
        $roles = $user['roles'] ?? [];

        if (is_array($roles[0] ?? null)) {
            $nombresRoles = array_column($roles, 'name');
        } else {
            $nombresRoles = is_array($roles) ? $roles : [];
        }

        if (empty($nombresRoles)) {
            $nombresRoles = array_filter([$user['role'] ?? $user['rol'] ?? null]);
        }

        $esAdmin = in_array('admin', $nombresRoles, true);
        $esEncargado = in_array('encargado', $nombresRoles, true);
        $esPersonal = $esAdmin || $esEncargado;

        $vistaCatalogo = request()->query('vista') === 'catalogo' || request()->routeIs('catalogo.*');
        $esRutaEquipos = request()->routeIs('equipos.*');
        // El modo "ver catálogo como solicitante" solo aplica al admin; el
        // encargado conserva su menú de personal aunque esté en el catálogo.
        $modoAdminSidebar = $esPersonal && !($esAdmin && $vistaCatalogo);

        // H-06: la miga superior debe reflejar la pantalla, no "Gestión de equipos".
        $tituloRuta = match (true) {
            request()->routeIs('inicio') => 'Inicio',
            request()->routeIs('prestamos.solicitudes') => 'Solicitudes pendientes',
            request()->routeIs('prestamos.entregas') => 'Entregas pendientes',
            request()->routeIs('prestamos.devoluciones') => 'Devoluciones',
            request()->routeIs('prestamos.mis-activos') => 'Mis préstamos',
            request()->routeIs('incidencias.*') => 'Incidencias',
            request()->routeIs('usuarios.*') => 'Usuarios',
            request()->routeIs('configuracion.*') => 'Configuración',
            request()->routeIs('roles.*') => 'Roles y cupos',
            request()->routeIs('equipos.*') => $esAdmin ? 'Equipos' : 'Catálogo de equipos',
            default => $title ?? 'Gestión de equipos',
        };

        $tituloPagina = $vistaCatalogo ? 'Catálogo de equipos' : $tituloRuta;
    @endphp

    {{-- Sidebar Lateral --}}
    <aside
        :class="menuAbierto ? 'translate-x-0' : 'max-md:-translate-x-full'"
        class="fixed md:static inset-y-0 left-0 w-64 bg-[#0B3D91] text-white flex flex-col justify-between shrink-0 h-screen z-40 select-none transition-transform duration-200">
        
        <div class="flex flex-col flex-1 overflow-y-auto">

            {{-- Header Institucional con Logo --}}
            <div class="px-6 py-5 border-b border-blue-900/50 shrink-0">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center font-black text-xs tracking-wider border border-white/20">
                        ECCI
                    </div>
                    <div>
                        <span class="text-xs font-bold tracking-tight text-white block leading-tight">Universidad ECCI</span>
                        <span class="text-[10px] text-blue-300 font-medium block">Sistema de Préstamo de Equipos</span>
                    </div>
                </div>

                {{-- Badge de Rol: Solicitante, Encargado o Administrador --}}
                <div class="mt-4">
                    @if($modoAdminSidebar)
                        <span class="inline-block px-2.5 py-1 bg-blue-500/20 border border-blue-400/30 rounded-md text-[11px] font-semibold text-blue-200">
                            {{ $esAdmin ? 'Administrador' : 'Encargado' }}
                        </span>
                    @else
                        <span class="inline-block px-2.5 py-1 bg-white/10 border border-white/15 rounded-md text-[11px] font-medium text-blue-100">
                            Solicitante
                        </span>
                    @endif
                </div>
            </div>

            {{-- Navegación Principal --}}
            <div class="px-4 py-5 flex-1 space-y-6">
                <div>
                    <span class="px-3 text-[10px] font-bold uppercase tracking-wider text-blue-300/60">Menú</span>
                    <nav class="mt-2 space-y-1 text-xs font-medium">

                        {{-- Inicio --}}
                        <a href="{{ route('inicio') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                            <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                            <span>Inicio</span>
                        </a>

                        {{-- SI ES MODO SOLICITANTE / CATALOGO (Fiel al Mockup HU-05) --}}
                        @if(!$modoAdminSidebar)
                            {{-- Catálogo de equipos (Activo) --}}
                            <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl bg-white/15 text-white font-semibold shadow-sm">
                                <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                <span>Catálogo de equipos</span>
                            </a>

                            {{-- Mis préstamos vigentes (HU-10 / KAN-120) --}}
                            <a href="{{ route('prestamos.mis-activos') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('prestamos.mis-activos') ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                <svg class="w-4 h-4 {{ request()->routeIs('prestamos.mis-activos') ? 'text-white' : 'text-blue-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Mis préstamos</span>
                            </a>

                        @else
                        {{-- MODO PERSONAL: administrador o encargado --}}
                            @if($esAdmin)
                                {{-- Gestión Equipos (solo administrador) --}}
                                <a href="{{ route('equipos.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ $esRutaEquipos && !$vistaCatalogo ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                    <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                    <span>Equipos</span>
                                </a>
                            @else
                                {{-- Catálogo de equipos (encargado) --}}
                                <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ $vistaCatalogo || $esRutaEquipos ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                    <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                    <span>Catálogo de equipos</span>
                                </a>
                            @endif

                            {{-- Préstamos Pendientes --}}
                            <a href="{{ route('prestamos.entregas') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                <span>Préstamos</span>
                            </a>

                            {{-- Devoluciones --}}
                            <a href="{{ route('prestamos.devoluciones') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                                <svg class="w-4 h-4 {{ request()->routeIs('prestamos.devoluciones') ? 'text-white' : 'text-blue-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                <span>Devoluciones</span>
                            </a>

                            {{-- Incidencias (RN-08) --}}
                            <a href="{{ route('incidencias.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('incidencias.*') ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                <svg class="w-4 h-4 {{ request()->routeIs('incidencias.*') ? 'text-white' : 'text-blue-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                                <span>Incidencias</span>
                            </a>

                            {{-- Solicitudes --}}
                            <a href="{{ route('prestamos.solicitudes') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-blue-200 hover:bg-white/10 hover:text-white transition">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span>Solicitudes</span>
                            </a>

                            @if($esAdmin)
                            {{-- Usuarios --}}
                            <a href="{{ route('usuarios.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('usuarios.*') ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                                <span>Usuarios</span>
                            </a>

                            {{-- RN-02: roles personalizados y cupos (solo administrador) --}}
                            <a href="{{ route('roles.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('roles.*') ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                                <span>Roles y cupos</span>
                            </a>

                            {{-- Configuración de reglas de negocio (iteración 2, solo administrador) --}}
                            <a href="{{ route('configuracion.index') }}" class="flex items-center gap-3 px-3 py-2.5 rounded-xl {{ request()->routeIs('configuracion.*', 'categorias.*') ? 'bg-white/15 text-white font-semibold shadow-sm' : 'text-blue-200 hover:bg-white/10 hover:text-white' }} transition">
                                <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                <span>Configuración</span>
                            </a>

                            @endif
                        @endif

                    </nav>
                </div>
            </div>

            {{-- Botón "Ver como administrador" si estamos en vista catálogo y el usuario es admin --}}
            @if($esAdmin && $vistaCatalogo)
                <div class="px-4 pb-2">
                    <a href="{{ route('equipos.index') }}" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs font-medium text-blue-200 hover:bg-white/10 hover:text-white transition border border-white/10">
                        <svg class="w-4 h-4 text-blue-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                        <span>Ver como administrador</span>
                    </a>
                </div>
            @endif
        </div>

        {{-- Footer Sidebar: Perfil del Usuario --}}
        <div class="p-4 border-t border-blue-900/60 flex items-center justify-between bg-[#082E6D] shrink-0">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-8 h-8 rounded-full bg-blue-500/40 border border-blue-400/40 flex items-center justify-center font-bold text-xs text-white shrink-0">
                    {{ strtoupper(substr($user['name'] ?? 'US', 0, 2)) }}
                </div>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-white truncate">{{ $user['name'] ?? 'Usuario' }}</p>
                    <p class="text-[10px] text-blue-300 truncate">{{ $user['email'] ?? 'usuario@ecci.edu.co' }}</p>
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

    {{-- Contenedor Principal --}}
    <div class="flex-1 flex flex-col min-w-0 h-screen overflow-hidden bg-[#F8FAFC]">

        {{-- Topbar Superior con Breadcrumb sincronizado --}}
        <header class="h-14 bg-white border-b border-slate-200/80 px-4 md:px-8 flex justify-between items-center shrink-0 z-10">
            <div class="flex items-center gap-3 text-xs text-slate-400 min-w-0">
                <button type="button" @click="menuAbierto = !menuAbierto" aria-label="Abrir menú" :aria-expanded="menuAbierto" class="md:hidden p-2 -ml-2 text-slate-500 hover:text-slate-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <span class="hidden sm:inline">Sistema</span>
                <span class="hidden sm:inline">&gt;</span>
                <span class="text-slate-700 font-medium truncate">
                    {{ $tituloPagina }}
                </span>
            </div>
            <div class="flex items-center gap-4">
                <button type="button" aria-label="Notificaciones" title="Notificaciones" class="text-slate-400 hover:text-slate-600 transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                </button>
                <div class="w-7 h-7 rounded-full bg-[#0B3D91] text-white flex items-center justify-center font-bold text-[10px]">
                    {{ strtoupper(substr($user['name'] ?? 'US', 0, 2)) }}
                </div>
            </div>
        </header>

        {{-- Vista de contenido --}}
        <main id="contenido-principal" tabindex="-1" class="flex-1 overflow-y-auto px-4 md:px-8 py-6 focus:outline-none">
            <div class="max-w-[1400px] mx-auto w-full">
                @yield('content')
            </div>
        </main>
    </div>

</body>
</html>
