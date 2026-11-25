@extends('layouts.app')

@section('content')
<div x-data="{
    tabActual: 'pendientes',
    search: '',
    openModalDevolucion: false,
    selectedPrestamo: {
        id: null,
        solicitante: '',
        equipo: '',
        codigo: '',
        fecha_entrega: '',
        fecha_devolucion: '',
        dias_retraso: 0
    },
    condicion: 'bueno',
    observaciones: '',
    abrirModal(item) {
        this.selectedPrestamo = { ...item };
        this.condicion = 'bueno';
        this.observaciones = '';
        this.openModalDevolucion = true;
    }
}" class="max-w-7xl mx-auto space-y-6">

    {{-- Cabecera --}}
    <div>
        <h2 class="text-2xl font-bold text-texto">Devoluciones</h2>
        <p class="text-sm text-texto-suave">Registra la devolución de equipos prestados, el estado en que son devueltos y las observaciones correspondientes.</p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1">
            <p class="font-bold">{{ $errors->first() }}</p>
        </div>
    @endif

    @if(isset($errorConexion))
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    {{-- Tarjetas de Métricas Superiores (Mockup HU-11) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
        {{-- Tarjeta 1: Pendientes de devolución --}}
        <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-marca-500 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $totalPendientes }}</span>
                <p class="text-xs font-semibold text-slate-700">Pendientes de devolución</p>
                <p class="text-xs text-marca-500 font-medium">Préstamos activos sin devolver</p>
            </div>
        </div>

        {{-- Tarjeta 2: Devoluciones registradas --}}
        <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $totalDevueltos }}</span>
                <p class="text-xs font-semibold text-slate-700">Devoluciones registradas</p>
                <p class="text-xs text-emerald-600 font-medium">Préstamos cerrados</p>
            </div>
        </div>
    </div>

    {{-- Tabs de Navegación --}}
    <div class="flex items-center gap-3 border-b border-slate-200">
        <button 
            type="button" 
            @click="tabActual = 'pendientes'" 
            :class="tabActual === 'pendientes' ? 'border-marca-500 text-marca-500 font-bold bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="px-4 py-2.5 text-sm border-b-2 font-medium transition flex items-center gap-2"
        >
            <span>Pendientes de devolución</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-marca-500 font-bold">{{ $totalPendientes }}</span>
        </button>

        <button 
            type="button" 
            @click="tabActual = 'historial'" 
            :class="tabActual === 'historial' ? 'border-marca-500 text-marca-500 font-bold bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="px-4 py-2.5 text-sm border-b-2 font-medium transition flex items-center gap-2"
        >
            <span>Historial de devoluciones</span>
            <span class="px-2 py-0.5 rounded-full text-xs bg-slate-100 text-slate-600 font-bold">{{ $totalDevueltos }}</span>
        </button>
    </div>

    {{-- TAB 1: PRÉSTAMOS PENDIENTES DE DEVOLUCIÓN --}}
    <div x-show="tabActual === 'pendientes'" class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        <div class="p-6 border-b border-borde flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Préstamos pendientes de devolución</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $totalPendientes }} {{ $totalPendientes === 1 ? 'registro' : 'registros' }}</p>
            </div>
        </div>

        {{-- Buscador reactivo --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-borde">
            <div class="relative max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por usuario o equipo..." class="w-full h-11 pl-9 pr-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
            </div>
        </div>

        @php
            // H-27: los datos se preparan una sola vez para la tabla (escritorio) y las
            // tarjetas (< 1280 px), y para el modal de devolución.
            $filasDevolucion = collect($pendientes)->map(function ($item) {
                $solicitanteNombre = $item['solicitante']['name'] ?? ($item['usuario']['name'] ?? 'Solicitante');
                $solicitanteEmail = $item['solicitante']['email'] ?? ($item['usuario']['email'] ?? 'N/D');
                $equipoNombre = $item['equipo']['nombre'] ?? 'Equipo';
                $equipoCodigo = $item['equipo']['codigo'] ?? 'S/C';
                // H-20: un solo formato de fecha en la app, d/m/Y con Carbon en la
                // zona de negocio, para que la fecha y la mora hablen del mismo día.
                $fechaEntrega = \App\Support\FechaNegocio::fecha($item['fecha_entrega_real'] ?? $item['fecha_inicio'] ?? null);
                $fechaEsperada = \App\Support\FechaNegocio::fecha($item['fecha_devolucion_estimada'] ?? null);

                // KAN-121: un préstamo entregado con la fecha estimada vencida está
                // en mora; los días se cuentan en calendario de Bogotá.
                $diasRetraso = \App\Support\FechaNegocio::diasDeRetraso(
                    strtolower($item['estado'] ?? 'entregado'),
                    $item['fecha_devolucion_estimada'] ?? null
                );

                return [
                    'solicitante' => $solicitanteNombre,
                    'iniciales' => strtoupper(substr($solicitanteNombre, 0, 2)),
                    'email' => $solicitanteEmail,
                    'equipo' => $equipoNombre,
                    'codigo' => $equipoCodigo,
                    'fecha_entrega' => $fechaEntrega,
                    'fecha_devolucion' => $fechaEsperada,
                    'estado' => $diasRetraso > 0 ? 'retrasado' : 'entregado',
                    'retraso' => $diasRetraso,
                    'dias_retraso_texto' => $diasRetraso === 1 ? '1 día de retraso' : $diasRetraso.' días de retraso',
                    'busqueda' => [
                        'solicitante' => strtolower($solicitanteNombre),
                        'equipo' => strtolower($equipoNombre),
                        'codigo' => strtolower($equipoCodigo),
                    ],
                    'json' => [
                        'id' => $item['id'],
                        'solicitante' => $solicitanteNombre,
                        'equipo' => $equipoNombre,
                        'codigo' => $equipoCodigo,
                        'fecha_entrega' => $fechaEntrega,
                        'fecha_devolucion' => $fechaEsperada,
                        'dias_retraso' => $diasRetraso,
                    ],
                ];
            });
        @endphp

        {{-- Escritorio (≥1280 px): tabla compacta con la acción fija a la derecha (H-27) --}}
        <div class="hidden xl:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                {{-- H-22: sin filas no se muestra el encabezado de columnas --}}
                @if($filasDevolucion->isNotEmpty())
                <thead>
                    <tr class="border-b border-borde text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-3">Solicitante</th>
                        <th class="py-3 px-3">Equipo</th>
                        {{-- El estado va antes de las fechas: es el dato que se mira
                             primero y a la derecha quedaba tapado por la columna fija. --}}
                        <th class="py-3 px-3">Estado</th>
                        <th class="py-3 px-3">F. Entrega</th>
                        <th class="py-3 px-3">F. Devolución Esperada</th>
                        {{-- Fondo opaco: translúcido dejaba ver el rótulo «ESTADO»
                             por debajo de la columna fija al desplazar la tabla. --}}
                        <th class="py-3 px-3 text-right sticky right-0 bg-slate-50">Acciones</th>
                    </tr>
                </thead>
                @endif
                <tbody class="divide-y divide-slate-100">
                    @forelse($filasDevolucion as $fila)
                        <tr
                            x-show="search === '' ||
                                    @js($fila['busqueda']['solicitante']).includes(search.toLowerCase()) ||
                                    @js($fila['busqueda']['equipo']).includes(search.toLowerCase()) ||
                                    @js($fila['busqueda']['codigo']).includes(search.toLowerCase())"
                            class="hover:bg-slate-50/80 transition {{ $fila['retraso'] > 0 ? 'bg-rose-50/30' : '' }}"
                        >
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-marca-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ $fila['iniciales'] }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800">{{ $fila['solicitante'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $fila['email'] }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3.5 px-3">
                                <p class="font-semibold text-slate-700">{{ $fila['equipo'] }}</p>
                                <span class="font-mono text-xs text-slate-400">{{ $fila['codigo'] }}</span>
                            </td>

                            <td class="py-3.5 px-3">
                                <x-estado :estado="$fila['estado']" />
                                @if($fila['retraso'] > 0)
                                    <p class="mt-1 text-xs font-semibold text-rose-600">{{ $fila['dias_retraso_texto'] }}</p>
                                @endif
                            </td>

                            <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">{{ $fila['fecha_entrega'] }}</td>
                            <td class="py-3.5 px-3 whitespace-nowrap {{ $fila['retraso'] > 0 ? 'font-semibold text-rose-700' : 'text-slate-600' }}">{{ $fila['fecha_devolucion'] }}</td>

                            <td class="py-3.5 px-3 text-right sticky right-0 bg-white">
                                <button
                                    type="button"
                                    @click="abrirModal(@js($fila['json']))"
                                    class="h-9 px-3.5 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm whitespace-nowrap"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Registrar devolución</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <p class="text-sm font-medium text-slate-500">No hay préstamos activos pendientes de devolución.</p>
                                    <p class="text-xs text-slate-400 max-w-xs">Cuando entregues un equipo, aparecerá aquí hasta que se registre su devolución.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Tarjetas por debajo de 1280 px: una por devolución, con el estado en la
             primera línea. A 1024 px la tabla mide 953 px, desborda el contenedor y
             la columna fija de acciones tapaba la píldora de estado por completo. --}}
        <div class="xl:hidden divide-y divide-slate-100">
            @forelse($filasDevolucion as $fila)
                <div
                    x-show="search === '' ||
                            @js($fila['busqueda']['solicitante']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['equipo']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['codigo']).includes(search.toLowerCase())"
                    class="p-4 space-y-3 {{ $fila['retraso'] > 0 ? 'bg-rose-50/40' : '' }}"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-marca-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $fila['iniciales'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $fila['solicitante'] }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $fila['email'] }}</p>
                        </div>
                        <x-estado :estado="$fila['estado']" class="shrink-0" />
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-slate-400">Equipo</dt>
                            <dd class="font-semibold text-slate-700">{{ $fila['equipo'] }}</dd>
                            <dd class="font-mono text-slate-400">{{ $fila['codigo'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. entrega</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_entrega'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. devolución esperada</dt>
                            <dd class="{{ $fila['retraso'] > 0 ? 'font-semibold text-rose-700' : 'text-slate-600' }}">{{ $fila['fecha_devolucion'] }}</dd>
                        </div>
                    </dl>

                    {{-- KAN-121: la mora no puede verse igual que un préstamo en plazo. --}}
                    @if($fila['retraso'] > 0)
                        <div class="flex items-start gap-2 p-3 rounded-[10px] bg-rose-50 border border-rose-200 text-xs text-rose-700">
                            <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p>Este préstamo lleva <strong>{{ $fila['dias_retraso_texto'] }}</strong>.</p>
                        </div>
                    @endif

                    <button
                        type="button"
                        @click="abrirModal(@js($fila['json']))"
                        class="w-full h-12 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] inline-flex items-center justify-center gap-1.5 transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Registrar devolución</span>
                    </button>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <div class="flex flex-col items-center gap-3 text-slate-400">
                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <p class="text-sm font-medium text-slate-500">No hay préstamos activos pendientes de devolución.</p>
                        <p class="text-xs text-slate-400 max-w-xs">Cuando entregues un equipo, aparecerá aquí hasta que se registre su devolución.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- TAB 2: HISTORIAL DE DEVOLUCIONES CERRADAS --}}
    <div x-show="tabActual === 'historial'" x-cloak class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        <div class="p-6 border-b border-borde">
            <h3 class="font-bold text-slate-900 text-base">Historial de devoluciones cerradas</h3>
            <p class="text-xs text-slate-400 mt-0.5">Registro de equipos que ya fueron retornados.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                {{-- H-22: sin filas no se muestra el encabezado de columnas --}}
                @if(count($historial) > 0)
                <thead>
                    <tr class="border-b border-borde text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Código</th>
                        <th class="py-3 px-6">F. Devolución Real</th>
                        <th class="py-3 px-6">Condición</th>
                        <th class="py-3 px-6">Estado</th>
                    </tr>
                </thead>
                @endif
                <tbody class="divide-y divide-slate-100">
                    @forelse($historial as $dev)
                        @php
                            $solic = $dev['solicitante']['name'] ?? ($dev['usuario']['name'] ?? 'Solicitante');
                            $eqNom = $dev['equipo']['nombre'] ?? 'Equipo';
                            $eqCod = $dev['equipo']['codigo'] ?? 'S/C';
                            // H-20: la devolución real conserva la hora (d/m/Y H:i).
                            $fDevReal = !empty($dev['fecha_devolucion_real']) ? \Illuminate\Support\Carbon::parse($dev['fecha_devolucion_real'])->format('d/m/Y H:i') : '—';
                            $cond = $dev['condicion_devolucion_etiqueta'] ?? ($dev['condicion_devolucion'] ?? 'Bueno');
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6 font-bold text-slate-800">{{ $solic }}</td>
                            <td class="py-3.5 px-6 font-semibold text-slate-700">{{ $eqNom }}</td>
                            <td class="py-3.5 px-6 font-mono text-slate-500">{{ $eqCod }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fDevReal }}</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-block px-2 py-0.5 rounded text-xs font-bold uppercase {{ str_contains(strtolower($cond), 'daño') || str_contains(strtolower($cond), 'mantenimiento') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $cond }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <x-estado estado="devuelto" />
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 13l4 4L19 7"/></svg>
                                    <p class="text-sm font-medium text-slate-500">Aún no hay devoluciones completadas registradas en el historial.</p>
                                    <p class="text-xs text-slate-400 max-w-xs">Cuando registres la devolución de un equipo, quedará listada aquí.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL PARA REGISTRAR DEVOLUCIÓN (HU-11) --}}
    <div x-show="openModalDevolucion" x-cloak @keydown.escape.window="openModalDevolucion = false" class="fixed inset-0 m-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalDevolucion = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-devolucion-titulo"
            x-ref="dialogoDevolucion"
            tabindex="-1"
            x-effect="openModalDevolucion && $nextTick(() => $refs.dialogoDevolucion.focus())"
            class="bg-white rounded-xl shadow-xl border border-borde w-full max-w-md overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-borde flex justify-between items-center bg-slate-50">
                <h3 id="modal-devolucion-titulo" class="font-bold text-slate-800 text-sm">Registrar devolución de equipo</h3>
                <button @click="openModalDevolucion = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/prestamos') }}/' + selectedPrestamo.id + '/devolucion'" method="POST" class="p-6 space-y-4">
                @csrf

                <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs space-y-1.5">
                    <p><strong class="text-slate-700">Solicitante:</strong> <span class="text-marca-700 font-bold" x-text="selectedPrestamo.solicitante"></span></p>
                    <p><strong class="text-slate-700">Equipo:</strong> <span class="text-slate-800 font-semibold" x-text="selectedPrestamo.equipo + ' (' + selectedPrestamo.codigo + ')'"></span></p>
                    <p><strong class="text-slate-700">F. Devolución esperada:</strong> <span class="text-slate-600" x-text="selectedPrestamo.fecha_devolucion"></span></p>
                </div>

                {{-- KAN-121: aviso de mora en el propio modal de devolución. --}}
                <div
                    x-show="selectedPrestamo.dias_retraso > 0"
                    x-cloak
                    class="flex items-start gap-2 p-3 rounded-[10px] bg-rose-50 border border-rose-200 text-xs text-rose-700"
                >
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p>
                        Este préstamo lleva
                        <strong x-text="selectedPrestamo.dias_retraso === 1 ? '1 día de retraso' : selectedPrestamo.dias_retraso + ' días de retraso'"></strong>.
                        Registra la devolución para cerrar la mora.
                    </p>
                </div>

                {{-- Condición física del equipo --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Condición del equipo al recibir <span class="text-rose-600">*</span></label>
                    <select name="condicion_devolucion" x-model="condicion" required class="w-full h-11 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none text-slate-700">
                        <option value="bueno">Bueno (Completamente funcional y sin daños)</option>
                        <option value="con_danos">Con daños (Golpes, rayones, piezas rotas)</option>
                        <option value="requiere_mantenimiento">Requiere mantenimiento técnico</option>
                    </select>
                </div>

                {{-- Observaciones (Obligatorias si no es 'bueno') --}}
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">
                        Observaciones de devolución 
                        <span x-show="condicion !== 'bueno'" class="text-rose-600 font-bold">* (Obligatorio)</span>
                        <span x-show="condicion === 'bueno'" class="text-slate-400 font-normal">(opcional)</span>
                    </label>
                    <textarea 
                        name="observaciones" 
                        x-model="observaciones"
                        :required="condicion !== 'bueno'"
                        rows="3" 
                        placeholder="Detalle el estado del equipo, accesorios devueltos o novedad detectada..."
                        class="w-full p-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700 text-slate-700"
                    ></textarea>
                </div>

                {{-- Nota dinámica según la regla de negocio --}}
                <div class="p-3 rounded-[10px] text-xs leading-relaxed border" :class="condicion === 'bueno' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                    <template x-if="condicion === 'bueno'">
                        <p>Al confirmar, el préstamo quedará cerrado como <strong>"Devuelto"</strong> y el equipo volverá a estar <strong>"Disponible"</strong> en el catálogo.</p>
                    </template>
                    <template x-if="condicion !== 'bueno'">
                        <p>Al confirmar con daños o mantenimiento, el préstamo quedará como <strong>"Devuelto"</strong> y el equipo pasará a <strong>"En mantenimiento"</strong> (no volverá a disponible hasta ser reparado). Además se abrirá una <strong>incidencia (RN-08)</strong> que restringe nuevos préstamos del equipo.</p>
                    </template>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalDevolucion = false" class="h-12 px-4 border border-borde text-slate-600 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="h-12 px-5 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Confirmar devolución</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
