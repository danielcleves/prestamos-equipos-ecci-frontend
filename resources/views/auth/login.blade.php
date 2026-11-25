@extends('layouts.guest')

@section('content')
    <div class="min-h-screen flex flex-col justify-between">

        {{-- Barra superior institucional --}}
        <header class="w-full py-3 px-6 bg-white/95 backdrop-blur border-b border-borde flex justify-between items-center text-xs text-texto-suave">
            <div class="flex items-center gap-2">
                <span class="font-bold text-marca-700 tracking-wide">Universidad ECCI</span>
                <span class="text-slate-300">|</span>
                <span>PRÉSTAMO DE EQUIPOS</span>
            </div>
            <div class="font-medium text-texto">
                Sistema de Préstamo de Equipos ECCI
                <span class="text-texto-suave font-normal ml-2 hidden sm:inline">Gestión de equipos tecnológicos y audiovisuales</span>
            </div>
        </header>

        {{-- Contenedor Principal --}}
        <main class="flex-1 grid grid-cols-1 lg:grid-cols-12 max-w-7xl w-full mx-auto p-4 lg:p-8 gap-8 items-center">

            {{-- Columna Izquierda: consejos (solo escritorio ≥1024 px) --}}
            <section id="panel-login" class="login-panel hidden lg:flex lg:col-span-7 relative overflow-hidden flex-col justify-between text-white rounded-3xl p-10 min-h-[34rem] border border-white/10" aria-label="Información de bienvenida">
                {{-- Decoración: cuadrícula tenue y anillos geométricos que giran muy despacio --}}
                <div class="login-decor" aria-hidden="true">
                    <span class="login-anillo login-anillo-1"></span>
                    <span class="login-anillo login-anillo-2"></span>
                    <span class="login-anillo login-anillo-3"></span>
                </div>

                <div class="relative">
                    <h1 class="text-4xl xl:text-5xl font-extrabold tracking-tight">Préstamo de <br>equipos ECCI</h1>
                    <p class="mt-3 text-blue-100 max-w-md text-sm">
                        Solicita y gestiona los equipos que necesitas para tus actividades académicas.
                    </p>
                </div>

                {{-- Consejos que rotan solos --}}
                <div class="relative" id="consejos" role="region" aria-label="Consejos de uso">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-100">Consejo</p>
                    @php
                        $consejos = [
                            'Tu cuenta se crea sola con tu correo @ecci.edu.co, pero el rol (profesor, coordinador…) lo asigna el administrador: pídeselo.',
                            'Cada rol tiene un cupo de préstamos activos. Si lo alcanzas, devuelve un equipo antes de pedir otro.',
                            'Devolver después de la fecha límite genera una sanción que puede limitar tus próximos préstamos.',
                            'Revisa el equipo y sus accesorios al recibirlo, y avisa en ese momento si algo no está bien.',
                            'El catálogo muestra qué equipos están disponibles antes de que hagas la solicitud.',
                            'Toda solicitud pasa por una aprobación antes de la entrega; sigue su estado en «Mis préstamos».',
                            'Algunos equipos requieren autorización del personal o una capacitación previa.',
                            'Pide solo el tiempo que necesitas: la duración máxima depende de la categoría del equipo y de tu rol.',
                            'Si olvidaste tu contraseña, pide al administrador que la restablezca.',
                        ];
                    @endphp
                    <div class="consejos-lista mt-2 grid">
                        @foreach($consejos as $i => $consejo)
                            <p class="consejo {{ $i === 0 ? 'is-activo' : '' }} text-xl xl:text-2xl font-medium leading-snug" data-consejo>{{ $consejo }}</p>
                        @endforeach
                    </div>
                    <div class="mt-5 flex gap-2" id="consejos-puntos" data-puntos>
                        @foreach($consejos as $i => $consejo)
                            <button type="button" class="consejo-punto {{ $i === 0 ? 'is-activo' : '' }}" data-punto="{{ $i }}" aria-label="Ver consejo {{ $i + 1 }} de {{ count($consejos) }}"></button>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Columna Derecha: Tarjeta de Formulario --}}
            <section class="lg:col-span-5 lg:col-start-8 bg-white rounded-2xl shadow-xl p-8 border border-borde">
                <div class="mb-6">
                    <h2 class="text-2xl font-bold text-texto">Iniciar sesión</h2>
                    <p class="text-xs text-texto-suave mt-1">Ingresa tus credenciales para acceder al sistema de préstamo de equipos.</p>
                </div>

                {{-- Mensajes de Error de Validación --}}
                @if ($errors->any())
                    <div class="mb-4 p-3 rounded-xl bg-error-suave border border-rose-200 text-error-texto text-sm">
                        {{ $errors->first() }}
                    </div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">
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
                                class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition"
                            />
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">
                            Contraseña
                        </label>
                        <input
                            id="password"
                            type="password"
                            name="password"
                            required
                            placeholder="••••••••••••"
                            class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition"
                        />
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <label class="flex items-center gap-2 text-texto-suave cursor-pointer">
                            <input type="checkbox" name="remember" class="rounded text-marca-700 focus:ring-marca-700">
                            <span>Recordarme</span>
                        </label>
                        <span class="text-texto-suave">Solicita el restablecimiento al administrador.</span>
                    </div>

                    <button
                        type="submit"
                        class="w-full h-12 px-4 bg-marca-700 hover:bg-marca-500 text-white font-medium rounded-[10px] text-sm shadow-md shadow-blue-950/20 transition duration-150">
                        Iniciar sesión
                    </button>
                </form>

                <p class="mt-5 text-center text-xs text-slate-500">
                    ¿No tienes cuenta?
                    <a href="{{ route('registro') }}" class="text-marca-700 font-semibold hover:underline">Crear cuenta</a>
                </p>
            </section>
        </main>

        {{-- Footer --}}
        <footer class="py-4 text-center text-xs text-slate-400 border-t border-white/5">
            © {{ date('Y') }} Universidad ECCI · Sistema de Préstamo de Equipos — Bogotá, Colombia
        </footer>

        {{-- Consejos que rotan en el panel (JS mínimo, sin dependencias) --}}
        <script>
            (function () {
                var panel = document.getElementById('panel-login');
                if (!panel) return;
                var reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                var items = panel.querySelectorAll('[data-consejo]');
                var puntos = panel.querySelectorAll('[data-punto]');
                var actual = 0, pausado = false, timer = null;
                function mostrar(n) {
                    items[actual].classList.remove('is-activo');
                    puntos[actual].classList.remove('is-activo');
                    actual = (n + items.length) % items.length;
                    items[actual].classList.add('is-activo');
                    puntos[actual].classList.add('is-activo');
                }
                if (reducir) {
                    // Sin movimiento: un consejo fijo y sin indicador de rotación
                    document.getElementById('consejos-puntos').style.display = 'none';
                    return;
                }
                function arrancar() { clearInterval(timer); timer = setInterval(function () { if (!pausado) mostrar(actual + 1); }, 6000); }
                panel.addEventListener('mouseenter', function () { pausado = true; });
                panel.addEventListener('mouseleave', function () { pausado = false; arrancar(); });
                puntos.forEach(function (b) {
                    b.addEventListener('click', function () { mostrar(Number(b.dataset.punto)); arrancar(); });
                });
                arrancar();
            })();
        </script>
    </div>
@endsection
