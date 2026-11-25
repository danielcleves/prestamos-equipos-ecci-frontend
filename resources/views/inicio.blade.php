@extends('layouts.app')

@section('content')
    {{--
        Panel de inicio (D-2). Las cifras llegan ya calculadas desde InicioController,
        consultadas a la API en cada visita; `null` significa que esa consulta falló.
    --}}
    @php
        $cifra = fn ($valor) => $valor === null ? '—' : number_format($valor, 0, ',', '.');
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Hola, {{ session('user')['name'] ?? 'usuario' }}
            </h1>
            <p class="text-sm text-slate-600">
                {{ $esPersonal ? 'Esto es lo que hay por atender hoy en recepción.' : 'Este es el estado de tus préstamos.' }}
            </p>
        </div>

        @if ($errors->has('inicio'))
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-lg">
                {{ $errors->first('inicio') }}
            </div>
        @endif

        @if ($hayFallos)
            <div role="alert" class="p-3 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-lg">
                No pudimos consultar parte de la información al servidor. Las cifras marcadas con «—» no están disponibles; recarga la página en unos segundos.
            </div>
        @endif

        {{-- Sanción vigente por mora: solo el solicitante la recibe (viene de /api/me). --}}
        @if (! empty($sancionActiva))
            <div role="alert" data-aviso="sancion" class="p-4 bg-error-suave border border-rose-200 text-error-texto text-sm rounded-[10px] flex items-start gap-3">
                <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                <p>
                    <span class="font-semibold">Tienes una sanción activa:</span>
                    {{ $sancionActiva['diasTexto'] === '1 día' ? 'queda 1 día' : 'quedan '.$sancionActiva['diasTexto'] }}, hasta el {{ $sancionActiva['fin'] }}.
                    No podrás solicitar equipos mientras dure.
                </p>
            </div>
        @endif

        @if ($esPersonal)
            @php
                $tarjetas = [
                    ['titulo' => 'Solicitudes por aprobar', 'valor' => $porAprobar, 'vacio' => 'Sin solicitudes por aprobar', 'ruta' => route('prestamos.solicitudes'), 'alerta' => false],
                    ['titulo' => 'Entregas pendientes', 'valor' => $porEntregar, 'vacio' => 'Sin entregas pendientes', 'ruta' => route('prestamos.entregas'), 'alerta' => false],
                    ['titulo' => 'Devoluciones pendientes', 'valor' => $porDevolver, 'vacio' => 'Sin devoluciones pendientes', 'ruta' => route('prestamos.devoluciones'), 'alerta' => false],
                    ['titulo' => 'Préstamos retrasados', 'valor' => $retrasados, 'vacio' => 'Ningún préstamo retrasado', 'ruta' => route('prestamos.devoluciones'), 'alerta' => true],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" data-panel="personal">
                @foreach ($tarjetas as $t)
                    <a href="{{ $t['ruta'] }}" class="block bg-white p-5 rounded-[10px] border border-borde shadow-sm hover:border-marca-700 transition">
                        <p class="text-sm font-semibold text-slate-600">{{ $t['titulo'] }}</p>
                        <p class="mt-1 text-3xl font-bold {{ $t['alerta'] && $t['valor'] ? 'text-error-texto' : 'text-marca-700' }}">{{ $cifra($t['valor']) }}</p>
                        <p class="mt-1 text-sm text-slate-600">
                            @if ($t['valor'] === null) No disponible
                            @elseif ($t['valor'] === 0) {{ $t['vacio'] }}
                            @else Ver lista
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>

            @if ($esAdmin)
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4" data-panel="admin">
                    <a href="{{ route('equipos.index') }}" class="block bg-white p-5 rounded-[10px] border border-borde shadow-sm hover:border-marca-700 transition">
                        <p class="text-sm font-semibold text-slate-600">Equipos disponibles</p>
                        <p class="mt-1 text-3xl font-bold text-marca-700">{{ $cifra($equiposDisponibles) }}<span class="text-lg font-semibold text-slate-600"> / {{ $cifra($equiposTotal) }}</span></p>
                        <p class="mt-1 text-sm text-slate-600">{{ $equiposTotal === null ? 'No disponible' : ($equiposTotal === 0 ? 'Aún no hay equipos registrados' : 'Ver catálogo') }}</p>
                    </a>
                    <a href="{{ route('incidencias.index') }}" class="block bg-white p-5 rounded-[10px] border border-borde shadow-sm hover:border-marca-700 transition">
                        <p class="text-sm font-semibold text-slate-600">Incidencias abiertas</p>
                        <p class="mt-1 text-3xl font-bold {{ $incidenciasAbiertas ? 'text-error-texto' : 'text-marca-700' }}">{{ $cifra($incidenciasAbiertas) }}</p>
                        <p class="mt-1 text-sm text-slate-600">{{ $incidenciasAbiertas === null ? 'No disponible' : ($incidenciasAbiertas === 0 ? 'Sin incidencias abiertas' : 'Ver incidencias') }}</p>
                    </a>
                    <a href="{{ route('usuarios.index') }}" class="block bg-white p-5 rounded-[10px] border border-borde shadow-sm hover:border-marca-700 transition">
                        <p class="text-sm font-semibold text-slate-600">Administración</p>
                        <p class="mt-1 text-lg font-bold text-marca-700">Gestión de usuarios</p>
                        <p class="mt-1 text-sm text-slate-600">Cuentas, roles y cupos</p>
                    </a>
                    <a href="{{ route('configuracion.index') }}" class="block bg-white p-5 rounded-[10px] border border-borde shadow-sm hover:border-marca-700 transition">
                        <p class="text-sm font-semibold text-slate-600">Reglas de negocio</p>
                        <p class="mt-1 text-lg font-bold text-marca-700">Configuración</p>
                        <p class="mt-1 text-sm text-slate-600">Parámetros de préstamo y sanciones</p>
                    </a>
                </div>
            @endif

            <section class="bg-white p-5 rounded-[10px] border border-borde shadow-sm" aria-labelledby="titulo-urgente">
                <h2 id="titulo-urgente" class="text-base font-bold text-slate-900">Lo más urgente</h2>
                @if (count($urgentes) === 0)
                    <p class="mt-3 text-sm text-slate-600">
                        {{ $hayFallos ? 'No pudimos cargar la lista.' : 'Nada urgente por ahora: no hay solicitudes, entregas ni devoluciones pendientes.' }}
                    </p>
                @else
                    <ul class="mt-3 divide-y divide-borde">
                        @foreach ($urgentes as $u)
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                <div class="flex items-center gap-3 sm:w-48 shrink-0">
                                    <x-estado :estado="$u['estado']" />
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">{{ $u['equipo'] }}@if ($u['codigo']) <span class="font-normal text-slate-600">· {{ $u['codigo'] }}</span>@endif</p>
                                    <p class="text-sm text-slate-600">
                                        {{ $u['solicitante'] ?? 'Solicitante' }} · Devolución: {{ $u['fecha'] }}
                                        @if ($u['dias'] > 0)
                                            · <span class="font-semibold text-error-texto">{{ $u['dias'] }} {{ $u['dias'] === 1 ? 'día' : 'días' }} de retraso</span>
                                        @endif
                                    </p>
                                </div>
                                <a href="{{ $u['ruta'] }}" class="text-sm font-semibold text-marca-700 hover:underline">{{ $u['accion'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @else
            <section class="bg-white p-5 rounded-[10px] border border-borde shadow-sm" aria-labelledby="titulo-mis" data-panel="solicitante">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="titulo-mis" class="text-base font-bold text-slate-900">Mis préstamos vigentes</h2>
                    <a href="{{ route('equipos.index') }}" class="text-sm font-semibold text-marca-700 hover:underline">Catálogo de equipos</a>
                </div>
                @if ($misRetrasados > 0)
                    <p class="mt-3 text-sm font-semibold text-error-texto">Tienes {{ $misRetrasados }} {{ $misRetrasados === 1 ? 'préstamo retrasado' : 'préstamos retrasados' }}: devuélvelo cuanto antes.</p>
                @endif
                @if (count($misPrestamos) === 0)
                    <p class="mt-3 text-sm text-slate-600">
                        {{ $hayFallos ? 'No pudimos cargar tus préstamos.' : 'No tienes préstamos vigentes. Explora el catálogo para solicitar un equipo.' }}
                    </p>
                @else
                    <ul class="mt-3 divide-y divide-borde">
                        @foreach ($misPrestamos as $p)
                            <li class="py-3 flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4">
                                <div class="sm:w-40 shrink-0"><x-estado :estado="$p['estado']" /></div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-semibold text-slate-900">{{ $p['equipo'] }}@if ($p['codigo']) <span class="font-normal text-slate-600">· {{ $p['codigo'] }}</span>@endif</p>
                                    <p class="text-sm {{ $p['dias'] > 0 ? 'text-error-texto font-semibold' : 'text-slate-600' }}">
                                        Devolver el {{ $p['fecha'] }}@if ($p['dias'] > 0) · {{ $p['dias'] }} {{ $p['dias'] === 1 ? 'día' : 'días' }} de retraso @endif
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @endif
    </div>
@endsection
