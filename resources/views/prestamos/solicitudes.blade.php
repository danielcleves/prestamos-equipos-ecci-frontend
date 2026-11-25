@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    fechaDesde: '',
    fechaHasta: '',
    filasFiltrables: [],
    openModalDetalle: false,
    openRechazo: false,
    motivoRechazo: '',
    selected: {
        id: null,
        solicitante: '',
        documento: '',
        fecha_solicitud: '',
        fecha_inicio: '',
        fecha_devolucion: '',
        estado: '',
        equipo_nombre: '',
        equipo_codigo: '',
        equipo_disponibilidad: '',
        categoria: '',
        requiere_autorizacion: false,
        autorizado: false,
        autorizado_por: '',
        estado_clases: '',
        estado_punto: '',
        disponibilidad_clases: { pildora: '', punto: '' }
    },
    abrirModal(item) {
        this.selected = { ...item };
        this.openRechazo = false;
        this.motivoRechazo = '';
        this.openModalDetalle = true;
    },
    limpiarFiltros() {
        this.search = '';
        this.fechaDesde = '';
        this.fechaHasta = '';
    },
    get hayFiltrosActivos() {
        return this.search !== '' || this.fechaDesde !== '' || this.fechaHasta !== '';
    },
    filaVisible(fSolicitud, solicitanteNorm, equipoNorm) {
        if (this.search !== '' && !solicitanteNorm.includes(this.search.toLowerCase()) && !equipoNorm.includes(this.search.toLowerCase())) {
            return false;
        }
        if (this.fechaDesde !== '' && fSolicitud < this.fechaDesde) {
            return false;
        }
        if (this.fechaHasta !== '' && fSolicitud > this.fechaHasta) {
            return false;
        }
        return true;
    },
    registrarFila(fila) {
        this.filasFiltrables.push(fila);
    },
    // Filas que pasan los filtros, calculadas sobre los mismos datos que usa cada
    // fila, para mostrar el aviso de sin resultados sin mirar el DOM.
    get filasVisibles() {
        return this.filasFiltrables.filter(
            (fila) => this.filaVisible(fila.fecha, fila.solicitante, fila.equipo)
        ).length;
    }
}" class="max-w-7xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <h2 class="text-2xl font-bold text-texto">Solicitudes pendientes</h2>
        <p class="text-sm text-texto-suave">Revisa las solicitudes pendientes de aprobación o rechazo.</p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- Error de conexión (KAN-113) --}}
    @if(isset($errorConexion))
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1">
            <p class="font-bold">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- Tabla de Solicitudes Pendientes --}}
    <div class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        {{-- Filtros (KAN-114) --}}
        <div class="p-4 bg-slate-50/50 border-b border-borde space-y-3">
            <div class="flex flex-wrap gap-3 items-center">
                {{-- Búsqueda por solicitante o equipo --}}
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input x-model="search" type="text" placeholder="Buscar por solicitante o equipo..." class="w-full h-11 pl-9 pr-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
                </div>

                {{-- Filtro por rango de fechas de solicitud --}}
                <div class="flex items-center gap-2">
                    <label for="fecha-desde" class="text-sm text-slate-500 shrink-0">Desde:</label>
                    <input id="fecha-desde" x-model="fechaDesde" type="date" aria-label="Fecha desde" class="h-11 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700 text-slate-700">
                </div>
                <div class="flex items-center gap-2">
                    <label for="fecha-hasta" class="text-sm text-slate-500 shrink-0">Hasta:</label>
                    <input id="fecha-hasta" x-model="fechaHasta" type="date" aria-label="Fecha hasta" class="h-11 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700 text-slate-700">
                </div>

                {{-- Limpiar filtros --}}
                <button
                    x-show="hayFiltrosActivos"
                    type="button"
                    @click="limpiarFiltros()"
                    class="h-11 px-4 text-sm text-slate-500 hover:text-rose-600 border border-slate-200 hover:border-rose-200 bg-white rounded-[10px] transition flex items-center gap-1.5"
                >
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    Limpiar filtros
                </button>
            </div>
        </div>

        @php
            // Los datos se preparan una sola vez para la tabla (escritorio) y las
            // tarjetas (móvil), y para el modal de detalle.
            $filasSolicitud = collect($solicitudes)->map(function ($item) {
                $solic = $item['solicitante']['name'] ?? ($item['usuario']['name'] ?? 'Usuario');
                $doc = $item['solicitante']['email'] ?? ($item['usuario']['email'] ?? 'N/D');
                $eqNom = $item['equipo']['nombre'] ?? 'Equipo';
                $eqCod = $item['equipo']['codigo'] ?? 'S/C';
                $eqDisp = strtolower($item['equipo']['estado'] ?? 'disponible') === 'disponible';
                $eqCat = $item['equipo']['categoria']['nombre'] ?? 'N/D';
                // El backend entrega ISO 8601 con desplazamiento (FechaNegocio) y la
                // app corre en UTC: hay que convertir a la zona de negocio, si no
                // una solicitud de las 22:00 de Bogotá aparece con el día siguiente.
                $fSol = ! empty($item['fecha_solicitud'])
                    ? \Illuminate\Support\Carbon::parse($item['fecha_solicitud'])->timezone('America/Bogota')->toDateString()
                    : now()->timezone('America/Bogota')->toDateString();
                $fIni = ! empty($item['fecha_inicio'])
                    ? \Illuminate\Support\Carbon::parse($item['fecha_inicio'])->timezone('America/Bogota')->toDateString()
                    : '—';
                $fFin = ! empty($item['fecha_devolucion_estimada'])
                    ? \Illuminate\Support\Carbon::parse($item['fecha_devolucion_estimada'])->timezone('America/Bogota')->toDateString()
                    : '—';
                $st = strtolower($item['estado'] ?? 'solicitado');

                // RN-03: solicitud de una categoría restringida que todavía
                // no ha sido autorizada por el personal.
                $requiereAutorizacion = filter_var($item['requiere_autorizacion'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $autorizado = filter_var($item['autorizado'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $autorizadoPor = $item['autorizado_por']['name'] ?? (is_string($item['autorizado_por'] ?? null) ? $item['autorizado_por'] : '');

                return [
                    'id' => $item['id'],
                    'solicitante' => $solic,
                    'documento' => $doc,
                    'equipo' => $eqNom,
                    'codigo' => $eqCod,
                    'estado' => $st,
                    'estado_etiqueta' => ucfirst($st),
                    // H-20: un solo formato de fecha en la app, d/m/Y.
                    'fecha_solicitud' => $fSol,
                    'fecha_inicio' => $fIni,
                    'fecha_devolucion' => $fFin,
                    'fecha_solicitud_fmt' => \Illuminate\Support\Carbon::parse($fSol)->format('d/m/Y'),
                    'fecha_inicio_fmt' => $fIni !== '—' ? \Illuminate\Support\Carbon::parse($fIni)->format('d/m/Y') : '—',
                    'fecha_devolucion_fmt' => $fFin !== '—' ? \Illuminate\Support\Carbon::parse($fFin)->format('d/m/Y') : '—',
                    'requiere_autorizacion' => $requiereAutorizacion,
                    'autorizado' => $autorizado,
                    'busqueda' => [
                        'solicitante' => strtolower($solic),
                        'equipo' => strtolower($eqNom).' '.strtolower($eqCod),
                    ],
                    // Datos del modal de detalle (HU-08).
                    'json' => [
                        'id' => $item['id'],
                        'solicitante' => $solic,
                        'documento' => $doc,
                        // H-20: el modal mostraba la fecha en ISO (2026-10-14).
                        'fecha_solicitud' => \Illuminate\Support\Carbon::parse($fSol)->format('d/m/Y'),
                        'fecha_inicio' => $fIni !== '—' ? \Illuminate\Support\Carbon::parse($fIni)->format('d/m/Y') : '—',
                        'fecha_devolucion' => $fFin !== '—' ? \Illuminate\Support\Carbon::parse($fFin)->format('d/m/Y') : '—',
                        'estado' => ucfirst($st),
                        'equipo_nombre' => $eqNom,
                        'equipo_codigo' => $eqCod,
                        'equipo_disponibilidad' => $eqDisp ? 'Disponible' : 'No disponible',
                        'categoria' => $eqCat,
                        'requiere_autorizacion' => $requiereAutorizacion,
                        'autorizado' => $autorizado,
                        'autorizado_por' => $autorizadoPor,
                        // Misma píldora que usa <x-estado> (guía UI/UX §15).
                        'estado_clases' => \App\Support\EstadoPildora::clases('prestamo', $st)['pildora'],
                        'estado_punto' => \App\Support\EstadoPildora::clases('prestamo', $st)['punto'],
                        'disponibilidad_clases' => \App\Support\EstadoPildora::clases('equipo', $eqDisp ? 'disponible' : 'no_disponible'),
                    ],
                ];
            });
        @endphp

        {{-- Escritorio: tabla con la acción fija a la derecha --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-borde text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">#</th>
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        {{-- El estado va antes de las fechas: es el dato que se mira
                             primero y a la derecha quedaba tapado por la columna fija. --}}
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6">F. Solicitud</th>
                        <th class="py-3 px-6">F. Inicio</th>
                        <th class="py-3 px-6">F. Devolución</th>
                        <th class="py-3 px-6 text-right sticky right-0 bg-slate-50/70">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($filasSolicitud as $fila)
                        <tr
                            x-show="filaVisible(@js($fila['fecha_solicitud']), @js($fila['busqueda']['solicitante']), @js($fila['busqueda']['equipo']))"
                            x-init="registrarFila({ fecha: @js($fila['fecha_solicitud']), solicitante: @js($fila['busqueda']['solicitante']), equipo: @js($fila['busqueda']['equipo']) })"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-mono text-slate-400">#{{ $fila['id'] }}</td>
                            <td class="py-3.5 px-6">
                                <p class="font-bold text-slate-800">{{ $fila['solicitante'] }}</p>
                                <p class="text-xs text-slate-400">{{ $fila['documento'] }}</p>
                            </td>
                            <td class="py-3.5 px-6">
                                <p class="font-semibold text-slate-700">{{ $fila['equipo'] }}</p>
                                <span class="font-mono text-slate-400 text-xs">{{ $fila['codigo'] }}</span>
                            </td>
                            <td class="py-3.5 px-6">
                                <x-estado :estado="$fila['estado']" />

                                @if($fila['requiere_autorizacion'] && ! $fila['autorizado'])
                                    <span class="inline-flex items-center gap-1 mt-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Requiere autorización
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fila['fecha_solicitud_fmt'] }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fila['fecha_inicio_fmt'] }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fila['fecha_devolucion_fmt'] }}</td>
                            <td class="py-3.5 px-6 text-right sticky right-0 bg-white">
                                <button
                                    type="button"
                                    @click="abrirModal(@js($fila['json']))"
                                    class="h-9 px-3.5 bg-slate-100 hover:bg-marca-700 hover:text-white text-slate-700 text-sm font-semibold rounded-[10px] transition inline-flex items-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Ver detalle</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-slate-400">
                                No hay solicitudes pendientes.
                            </td>
                        </tr>
                    @endforelse

                    {{-- Fila dinámica: sin resultados por filtros (KAN-114) --}}
                    @if(count($solicitudes) > 0)
                    <tr x-show="filasVisibles === 0" x-cloak>
                        <td colspan="8" class="py-8 text-center text-slate-400 text-xs">
                            No hay solicitudes que coincidan con los filtros aplicados.
                            <button type="button" @click="limpiarFiltros()" class="ml-2 text-marca-700 underline">Limpiar filtros</button>
                        </td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>

        {{-- Móvil: una tarjeta por solicitud, con el estado en su propia línea
             para que se lea sin desplazar la pantalla (guía UI/UX §9). --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($filasSolicitud as $fila)
                <div
                    x-show="filaVisible(@js($fila['fecha_solicitud']), @js($fila['busqueda']['solicitante']), @js($fila['busqueda']['equipo']))"
                    class="p-4 space-y-3"
                >
                    {{-- Solicitante --}}
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800 break-words">{{ $fila['solicitante'] }}</p>
                        <p class="text-xs text-slate-400 break-words">{{ $fila['documento'] }}</p>
                    </div>

                    {{-- Equipo con su código --}}
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-700 break-words">{{ $fila['equipo'] }}</p>
                        <span class="font-mono text-xs text-slate-400">{{ $fila['codigo'] }}</span>
                    </div>

                    {{-- Estado: misma píldora que la tabla (guía UI/UX §15) --}}
                    <div class="flex flex-wrap items-center gap-2">
                        <x-estado :estado="$fila['estado']" />

                        @if($fila['requiere_autorizacion'] && ! $fila['autorizado'])
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                Requiere autorización
                            </span>
                        @endif
                    </div>

                    {{-- Fechas en d/m/Y, como en la tabla --}}
                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-slate-400">F. solicitud</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_solicitud_fmt'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. inicio</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_inicio_fmt'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. devolución</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_devolucion_fmt'] }}</dd>
                        </div>
                    </dl>

                    {{-- Misma acción que la fila: abre el modal de detalle --}}
                    <button
                        type="button"
                        @click="abrirModal(@js($fila['json']))"
                        class="w-full h-12 bg-slate-100 hover:bg-marca-700 hover:text-white text-slate-700 text-sm font-semibold rounded-[10px] transition inline-flex items-center justify-center gap-1.5"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                        <span>Ver detalle</span>
                    </button>
                </div>
            @empty
                <div class="px-4 py-12 text-center text-slate-400">
                    No hay solicitudes pendientes.
                </div>
            @endforelse

            {{-- Sin resultados por filtros (KAN-114), igual que la fila de la tabla --}}
            @if(count($solicitudes) > 0)
                <div x-show="filasVisibles === 0" x-cloak class="px-4 py-8 text-center text-slate-400 text-xs">
                    No hay solicitudes que coincidan con los filtros aplicados.
                    <button type="button" @click="limpiarFiltros()" class="ml-2 text-marca-700 underline">Limpiar filtros</button>
                </div>
            @endif
        </div>
    </div>

    {{-- MODAL DETALLE DE SOLICITUD (HU-08) --}}
    <div x-show="openModalDetalle" x-cloak @keydown.escape.window="openModalDetalle = false" class="fixed inset-0 m-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalDetalle = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-detalle-solicitud-titulo"
            x-ref="dialogoDetalleSolicitud"
            tabindex="-1"
            x-foco-modal="openModalDetalle"
            x-effect="openModalDetalle && $nextTick(() => $refs.dialogoDetalleSolicitud.focus())"
            class="bg-white rounded-2xl shadow-2xl border border-borde w-full max-w-xl overflow-hidden animate-fade-in"
        >

            {{-- Header Modal --}}
            <div class="px-6 py-5 border-b border-borde flex justify-between items-start bg-white">
                <div>
                    <h3 id="modal-detalle-solicitud-titulo" class="font-bold text-slate-900 text-base">Detalle de solicitud</h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Solicitud #<span x-text="selected.id"></span> · <span x-text="selected.estado"></span>
                    </p>
                </div>
                <button @click="openModalDetalle = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>

            <div class="p-6 space-y-6 text-sm">
                {{-- Grid Datos Solicitante y Fechas --}}
                <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">SOLICITANTE</span>
                        <span class="font-bold text-slate-800 text-sm mt-0.5 block" x-text="selected.solicitante"></span>
                    </div>

                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">CORREO</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.documento"></span>
                    </div>

                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">FECHA DE SOLICITUD</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_solicitud"></span>
                    </div>

                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">FECHA REQUERIDA</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_inicio"></span>
                    </div>

                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">FECHA DE DEVOLUCIÓN</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_devolucion"></span>
                    </div>

                    <div>
                        <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">ESTADO</span>
                        <span class="inline-flex items-center gap-1.5 mt-0.5 px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap" :class="selected.estado_clases">
                            <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="selected.estado_punto" aria-hidden="true"></span>
                            <span x-text="selected.estado"></span>
                        </span>
                    </div>
                </div>

                {{-- RN-03: autorización especial de la categoría --}}
                <div x-show="selected.requiere_autorizacion" x-cloak class="p-4 rounded-xl border text-xs leading-relaxed"
                     :class="selected.autorizado ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                    <template x-if="!selected.autorizado">
                        <p>La categoría de este equipo es <strong>restringida</strong>: la solicitud necesita autorización adicional antes de poder aprobarse (RN-03).</p>
                    </template>
                    <template x-if="selected.autorizado">
                        <p>Solicitud <strong>autorizada</strong><span x-show="selected.autorizado_por" x-text="selected.autorizado_por ? ' por ' + selected.autorizado_por : ''"></span>. Ya puede aprobarse.</p>
                    </template>
                </div>

                {{-- Separador EQUIPO SOLICITADO --}}
                <div class="pt-4 border-t border-slate-100">
                    <div class="text-center mb-4">
                        <span class="text-xs uppercase font-bold text-slate-400 tracking-wider bg-white px-2">EQUIPO SOLICITADO</span>
                    </div>

                    <div class="grid grid-cols-2 gap-y-4 gap-x-6 bg-slate-50/70 p-4 rounded-xl border border-slate-100">
                        <div>
                            <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">EQUIPO</span>
                            <span class="font-bold text-slate-800 mt-0.5 block" x-text="selected.equipo_nombre"></span>
                        </div>

                        <div>
                            <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">CÓDIGO</span>
                            <span class="font-mono text-slate-700 mt-0.5 block font-bold" x-text="selected.equipo_codigo"></span>
                        </div>

                        <div>
                            <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">DISPONIBILIDAD</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border mt-0.5 whitespace-nowrap"
                                  :class="selected.disponibilidad_clases.pildora">
                                <span class="w-1.5 h-1.5 rounded-full shrink-0" :class="selected.disponibilidad_clases.punto" aria-hidden="true"></span>
                                <span x-text="selected.equipo_disponibilidad"></span>
                            </span>
                        </div>

                        <div>
                            <span class="text-xs uppercase font-bold text-slate-400 block tracking-wider">CATEGORÍA</span>
                            <span class="text-slate-700 mt-0.5 block" x-text="selected.categoria"></span>
                        </div>
                    </div>
                </div>

                {{-- Acciones del Modal --}}
                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-slate-100">
                    <button type="button" @click="openModalDetalle = false" class="h-12 px-4 border border-borde text-slate-600 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cerrar
                    </button>

                    <template x-if="selected.estado === 'Solicitado' || selected.estado === 'Pendiente'">
                        <div class="w-full sm:w-auto space-y-4">
                            {{-- Campo de motivo (requerido por el backend para rechazar) --}}
                            <div x-show="openRechazo" class="p-4 bg-rose-50/60 border border-rose-200 rounded-xl space-y-2">
                                <label for="motivoRechazo" class="block text-sm uppercase font-bold text-rose-700 tracking-wider">
                                    Motivo de rechazo <span class="text-rose-600">*</span>
                                </label>
                                <textarea
                                    id="motivoRechazo"
                                    x-model="motivoRechazo"
                                    rows="3"
                                    maxlength="1000"
                                    placeholder="Describe el motivo del rechazo (máx. 1000 caracteres)..."
                                    class="w-full text-sm bg-white border border-rose-200 rounded-[10px] p-3 text-slate-700 focus:outline-none focus:ring-1 focus:ring-rose-400 resize-none"
                                ></textarea>
                                <p class="text-xs text-rose-600" x-show="motivoRechazo.length > 900" x-text="(1000 - motivoRechazo.length) + ' caracteres restantes'"></p>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-3">
                                {{-- H-21: «Cancelar» solo aparece mientras se rechaza y deshace el rechazo;
                                     antes cerraba el modal y competía con «Cerrar». --}}
                                <button type="button" x-show="openRechazo" x-cloak @click="openRechazo = false" class="h-12 px-4 border border-borde text-slate-600 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                                    Cancelar rechazo
                                </button>

                                {{-- Formulario Rechazar --}}
                                <form x-show="!openRechazo" @submit.prevent="openRechazo = true" class="inline">
                                    <button type="submit" class="h-12 px-4 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-sm font-semibold rounded-[10px] transition flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Rechazar</span>
                                    </button>
                                </form>

                                <form x-show="openRechazo" :action="'{{ url('/prestamos') }}/' + selected.id + '/rechazar'" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="motivo" :value="motivoRechazo">
                                    <button type="submit" :disabled="motivoRechazo.trim().length < 1" :class="motivoRechazo.trim().length < 1 ? 'opacity-50 cursor-not-allowed' : ''" class="h-12 px-4 border border-rose-200 bg-rose-600 hover:bg-rose-700 text-white text-sm font-semibold rounded-[10px] transition flex items-center gap-1.5">
                                        <span>Confirmar rechazo</span>
                                    </button>
                                </form>

                                {{-- RN-03: autorizar la solicitud restringida --}}
                                <template x-if="selected.requiere_autorizacion && !selected.autorizado && !openRechazo">
                                    <form :action="'{{ url('/prestamos') }}/' + selected.id + '/autorizar'" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="h-12 px-4 border border-amber-300 bg-amber-50 hover:bg-amber-100 text-amber-800 text-sm font-semibold rounded-[10px] transition flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            <span>Autorizar</span>
                                        </button>
                                    </form>
                                </template>

                                {{-- Formulario Aprobar — solo si el equipo está disponible y la
                                     solicitud restringida ya fue autorizada (RN-03) --}}
                                <template x-if="selected.equipo_disponibilidad === 'Disponible' && !openRechazo && (!selected.requiere_autorizacion || selected.autorizado)">
                                    <form :action="'{{ url('/prestamos') }}/' + selected.id + '/aprobar'" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="requiere_autorizacion" :value="selected.requiere_autorizacion ? 1 : 0">
                                        <input type="hidden" name="autorizado" :value="selected.autorizado ? 1 : 0">
                                        <button type="submit" class="h-12 px-5 bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>Aprobar solicitud</span>
                                        </button>
                                    </form>
                                </template>

                                {{-- RN-03: la aprobación queda bloqueada hasta autorizar --}}
                                <template x-if="selected.equipo_disponibilidad === 'Disponible' && !openRechazo && selected.requiere_autorizacion && !selected.autorizado">
                                    <button type="button" disabled title="Autoriza la solicitud antes de aprobarla" class="h-12 px-5 bg-slate-100 text-slate-400 text-sm font-semibold rounded-[10px] cursor-not-allowed flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>Aprobar (requiere autorización)</span>
                                    </button>
                                </template>

                                {{-- Bloqueo de aprobación si el equipo no está disponible --}}
                                <template x-if="selected.equipo_disponibilidad !== 'Disponible' && !openRechazo">
                                    <button type="button" disabled title="No se puede aprobar porque el equipo no está disponible" class="h-12 px-5 bg-slate-100 text-slate-400 text-sm font-semibold rounded-[10px] cursor-not-allowed flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        <span>Equipo no disponible</span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection
