@extends('layouts.guest')

@section('content')
    <div class="min-h-screen flex flex-col justify-between">

        {{-- Barra superior institucional --}}
        <header class="w-full py-3 px-6 bg-white/95 backdrop-blur border-b border-[#D9DEE7] flex justify-between items-center text-xs text-[#6B7280]">
            <div class="flex items-center gap-2">
                <span class="font-bold text-[#0B3D91] tracking-wide">Universidad ECCI</span>
                <span class="text-slate-300">|</span>
                <span>PRÉSTAMO DE EQUIPOS</span>
            </div>
            <div class="font-medium text-[#1F2937]">
                Sistema de Préstamo de Equipos ECCI
                <span class="text-[#6B7280] font-normal ml-2 hidden sm:inline">Gestión de equipos tecnológicos y audiovisuales</span>
            </div>
        </header>

        {{-- Contenedor Principal --}}
        <main class="flex-1 grid grid-cols-1 lg:grid-cols-12 max-w-7xl w-full mx-auto p-4 lg:p-8 gap-8 items-center">

            {{-- Columna Izquierda: Banner & Equipos --}}
            <section class="lg:col-span-7 flex flex-col justify-center text-white space-y-6">
                <div>
                    <h1 class="text-4xl lg:text-5xl font-extrabold tracking-tight">Préstamo de <br>equipos ECCI</h1>
                    <p class="mt-3 text-slate-300 max-w-md text-sm">
                        Solicita y gestiona los equipos que necesitas para tus actividades académicas.
                    </p>
                </div>

                {{-- Grid de Categorías (Mockup) --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    @php
                        $categorias = [
                            'Portátiles', 'Cámaras', 'Proyectores', 'Micrófonos',
                            'Tablets', 'Auriculares', 'Iluminación', 'Enrutadores'
                        ];
                    @endphp
                    @foreach($categorias as $cat)
                        <div class="bg-white/10 hover:bg-white/15 backdrop-blur-sm border border-white/10 p-3 rounded-xl text-center transition cursor-default">
                            <span class="text-xs font-medium text-slate-200">{{ $cat }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Columna Derecha: Tarjeta de Formulario --}}
            <section class="lg:col-span-5 bg-white rounded-2xl shadow-xl p-8 border border-[#D9DEE7]">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-[#1F2937]">Iniciar sesión</h2>
                    <p class="text-xs text-[#6B7280] mt-1">Ingresa tus credenciales para acceder al sistema de préstamo de equipos.</p>
                </div>

                {{-- Mensajes de Error de Validación --}}
                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-xl bg-red-50 border border-red-200 text-[#EF4444] text-xs">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-bold text-[#1F2937] tracking-wider uppercase mb-1">
                            Usuario institucional
                        </label>
                        <div class="relative">
                            <input
                                id="email"
                                type="email"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                placeholder="ejemplo@ecci.edu.co"
                                class="w-full h-12 px-4 bg-white border border-[#D9DEE7] rounded-[10px] text-sm focus:ring-2 focus:ring-[#0B3D91] focus:border-transparent outline-none transition"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-bold text-[#1F2937] tracking-wider uppercase mb-1">
                            Contraseña
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            placeholder="••••••••••••"
                            class="w-full h-12 px-4 bg-white border border-[#D9DEE7] rounded-[10px] text-sm focus:ring-2 focus:ring-[#0B3D91] focus:border-transparent outline-none transition"
                        />
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-2 text-[#6B7280] cursor-pointer">
                            <input type="checkbox" name="remember" class="rounded text-[#0B3D91] focus:ring-[#0B3D91]">
                            <span>Recordarme</span>
                        </label>
                        <span class="text-[#6B7280]">Solicita el restablecimiento al administrador.</span>
                    </div>

                    <button
                        type="submit"
                        class="w-full h-12 px-4 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white font-medium rounded-[10px] text-sm shadow-md shadow-blue-950/20 transition duration-150">
                        Iniciar sesión
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-slate-500">
                    ¿No tienes cuenta?
                    <a href="{{ route('registro') }}" class="text-[#0B3D91] font-semibold hover:underline">Crear cuenta</a>
                </p>
            </section>
        </main>

        {{-- Footer --}}
        <footer class="py-4 text-center text-xs text-slate-400 border-t border-white/5">
            © {{ date('Y') }} Universidad ECCI · Sistema de Préstamo de Equipos — Bogotá, Colombia
        </footer>
    </div>
@endsection
