@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    openModalEntrega: false,
    selectedPrestamo: {
        id: null,
        solicitante: '',
        equipo: '',
        codigo: '',
        fecha_prestamo: '',
        fecha_devolucion: ''
    },
    abrirModal(item) {
        this.selectedPrestamo = { ...item };
        this.openModalEntrega = true;
    }
}" class="max-w-7xl mx-auto space-y-6">

    {{-- Cabecera --}}
    <div>
        <h2 class="text-2xl font-bold text-texto">Entregas pendientes</h2>
        <p class="text-sm text-texto-suave">Solicitudes aprobadas que están listas para que el equipo sea entregado físicamente al solicitante.</p>
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

    {{-- Tarjeta Métrica Superior (Mockup HU-09) --}}
    <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4 max-w-sm">
        <div class="w-12 h-12 rounded-xl bg-blue-50 text-marca-500 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM3 4h3l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        </div>
        <div>
            <span class="text-3xl font-extrabold text-slate-800">{{ $totalPendientes }}</span>
            <p class="text-xs font-semibold text-slate-700">Pendientes de entrega</p>
            <p class="text-xs text-marca-500 font-medium">Solicitudes aprobadas sin entregar</p>
        </div>
    </div>

    {{-- Tabla de Equipos por Entregar --}}
    <div class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        <div class="p-6 border-b border-borde flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Equipos por entregar</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $totalPendientes }} {{ $totalPendientes === 1 ? 'entrega pendiente' : 'entregas pendientes' }}</p>
            </div>
        </div>

        {{-- Buscador reactivo --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-borde">
            <div class="relative max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por solicitante o equipo..." class="w-full h-11 pl-9 pr-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
            </div>
        </div>

        @php
            // H-14: los datos se preparan una sola vez para la tabla (escritorio) y las
            // tarjetas (móvil), y para el modal de entrega.
            $filasEntrega = collect($prestamos)->map(function ($item) {
                $solicitanteNombre = $item['solicitante']['name'] ?? ($item['usuario']['name'] ?? 'Solicitante');
                $solicitanteDoc = $item['solicitante']['email'] ?? ($item['usuario']['email'] ?? 'N/D');
                $equipoNombre = $item['equipo']['nombre'] ?? 'Equipo';
                $equipoCodigo = $item['equipo']['codigo'] ?? 'S/C';
                // H-20: un solo formato de fecha en la app, d/m/Y con Carbon.
                $fechaPrestamo = ! empty($item['fecha_inicio'])
                    ? \Illuminate\Support\Carbon::parse($item['fecha_inicio'])->format('d/m/Y')
                    : '—';
                $fechaDevolucion = ! empty($item['fecha_devolucion_estimada'])
                    ? \Illuminate\Support\Carbon::parse($item['fecha_devolucion_estimada'])->format('d/m/Y')
                    : '—';

                return [
                    'solicitante' => $solicitanteNombre,
                    'iniciales' => strtoupper(substr($solicitanteNombre, 0, 2)),
                    'documento' => $solicitanteDoc,
                    'equipo' => $equipoNombre,
                    'codigo' => $equipoCodigo,
                    'fecha_prestamo' => $fechaPrestamo,
                    'fecha_devolucion' => $fechaDevolucion,
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
                        'fecha_prestamo' => $fechaPrestamo,
                        'fecha_devolucion' => $fechaDevolucion,
                    ],
                ];
            });
        @endphp

        {{-- Escritorio (≥1280 px): tabla compacta con la acción fija a la derecha (H-14) --}}
        <div class="hidden xl:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                {{-- H-22: sin filas no se muestra el encabezado de columnas --}}
                @if($filasEntrega->isNotEmpty())
                <thead>
                    <tr class="border-b border-borde text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-3">Solicitante</th>
                        <th class="py-3 px-3">Equipo</th>
                        <th class="py-3 px-3">Estado</th>
                        <th class="py-3 px-3">F. Préstamo</th>
                        <th class="py-3 px-3">F. Devolución</th>
                        <th class="py-3 px-3 text-right sticky right-0 bg-slate-50/70">Acciones</th>
                    </tr>
                </thead>
                @endif
                <tbody class="divide-y divide-slate-100">
                    @forelse($filasEntrega as $fila)
                        <tr
                            x-show="search === '' ||
                                    @js($fila['busqueda']['solicitante']).includes(search.toLowerCase()) ||
                                    @js($fila['busqueda']['equipo']).includes(search.toLowerCase()) ||
                                    @js($fila['busqueda']['codigo']).includes(search.toLowerCase())"
                            class="hover:bg-slate-50/80 transition"
                        >
                            {{-- Solicitante --}}
                            <td class="py-3.5 px-3">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ $fila['iniciales'] }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800">{{ $fila['solicitante'] }}</p>
                                        <p class="text-xs text-slate-400">{{ $fila['documento'] }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Equipo y código --}}
                            <td class="py-3.5 px-3">
                                <p class="font-semibold text-slate-700">{{ $fila['equipo'] }}</p>
                                <span class="font-mono text-xs text-slate-400">{{ $fila['codigo'] }}</span>
                            </td>

                            {{-- Estado: el componente x-estado unifica la píldora (guía UI/UX §15) --}}
                            <td class="py-3.5 px-3">
                                <x-estado estado="aprobado" texto="Pendiente de entrega" />
                            </td>

                            {{-- Fechas --}}
                            <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">{{ $fila['fecha_prestamo'] }}</td>
                            <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">{{ $fila['fecha_devolucion'] }}</td>

                            {{-- Botón Registrar entrega --}}
                            <td class="py-3.5 px-3 text-right sticky right-0 bg-white">
                                <button
                                    type="button"
                                    @click="abrirModal(@js($fila['json']))"
                                    class="h-9 px-3.5 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm whitespace-nowrap"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                    <span>Registrar entrega</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM3 4h3l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                                    <p class="text-sm font-medium text-slate-500">No hay entregas pendientes registradas en este momento.</p>
                                    <p class="text-xs text-slate-400 max-w-xs">Cuando se apruebe una solicitud, aparecerá aquí para registrar la entrega física.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Tarjetas por debajo de 1280 px: una por entrega, con el estado en la
             primera línea (guía UI/UX §9 y §6.4). A 1024 px la tabla mide 939 px,
             desborda los 702 px del contenedor y la columna fija de acciones tapa
             el final de la píldora de estado. --}}
        <div class="xl:hidden divide-y divide-slate-100">
            @forelse($filasEntrega as $fila)
                <div
                    x-show="search === '' ||
                            @js($fila['busqueda']['solicitante']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['equipo']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['codigo']).includes(search.toLowerCase())"
                    class="p-4 space-y-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $fila['iniciales'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $fila['solicitante'] }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $fila['documento'] }}</p>
                        </div>
                        <x-estado estado="aprobado" texto="Pendiente" class="shrink-0" />
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-xs">
                        <div>
                            <dt class="text-slate-400">Equipo</dt>
                            <dd class="font-semibold text-slate-700">{{ $fila['equipo'] }}</dd>
                            <dd class="font-mono text-slate-400">{{ $fila['codigo'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">Estado</dt>
                            <dd class="text-slate-600">Pendiente de entrega</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. préstamo</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_prestamo'] }}</dd>
                        </div>
                        <div>
                            <dt class="text-slate-400">F. devolución</dt>
                            <dd class="text-slate-600">{{ $fila['fecha_devolucion'] }}</dd>
                        </div>
                    </dl>

                    <button
                        type="button"
                        @click="abrirModal(@js($fila['json']))"
                        class="w-full h-12 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] inline-flex items-center justify-center gap-1.5 transition shadow-sm"
                    >
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Registrar entrega</span>
                    </button>
                </div>
            @empty
                <div class="px-4 py-12 text-center">
                    <div class="flex flex-col items-center gap-3 text-slate-400">
                        <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM3 4h3l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
                        <p class="text-sm font-medium text-slate-500">No hay entregas pendientes registradas en este momento.</p>
                        <p class="text-xs text-slate-400 max-w-xs">Cuando se apruebe una solicitud, aparecerá aquí para registrar la entrega física.</p>
                    </div>
                </div>
            @endforelse
        </div>
    </div>

    {{-- MODAL PARA REGISTRAR ENTREGA (HU-09) --}}
    <div x-show="openModalEntrega" x-cloak @keydown.escape.window="openModalEntrega = false" class="fixed inset-0 m-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalEntrega = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-entrega-titulo"
            x-ref="dialogoEntrega"
            tabindex="-1"
            x-foco-modal="openModalEntrega"
            x-effect="openModalEntrega && $nextTick(() => $refs.dialogoEntrega.focus())"
            class="bg-white rounded-xl shadow-xl border border-borde w-full max-w-md overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-borde flex justify-between items-center bg-slate-50">
                <h3 id="modal-entrega-titulo" class="font-bold text-slate-800 text-sm">Confirmar entrega de equipo</h3>
                <button @click="openModalEntrega = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/prestamos') }}/' + selectedPrestamo.id + '/entrega'" method="POST" class="p-6 space-y-4">
                @csrf

                <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs space-y-1.5">
                    <p><strong class="text-slate-700">Solicitante:</strong> <span class="text-marca-700 font-bold" x-text="selectedPrestamo.solicitante"></span></p>
                    <p><strong class="text-slate-700">Equipo:</strong> <span class="text-slate-800 font-semibold" x-text="selectedPrestamo.equipo + ' (' + selectedPrestamo.codigo + ')'"></span></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Condición del equipo al entregar <span class="text-rose-600">*</span></label>
                    <select name="condicion_entrega" required class="w-full h-11 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none text-slate-700">
                        <option value="bueno" selected>Bueno (Completamente funcional)</option>
                        <option value="con_danos">Con daños menores</option>
                        <option value="requiere_mantenimiento">Requiere mantenimiento</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1.5">Observaciones de entrega <span class="text-slate-400 font-normal">(opcional)</span></label>
                    <textarea 
                        name="observaciones" 
                        rows="3" 
                        placeholder="Accesorios entregados (cargador, mouse, estuche), estado estético..."
                        class="w-full p-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700 text-slate-700"
                    ></textarea>
                </div>

                <div class="p-3 bg-amber-50 rounded-[10px] border border-amber-200 text-amber-800 text-sm leading-relaxed">
                    Al confirmar, el préstamo cambiará a <strong>"Entregado"</strong> y el equipo pasará al estado <strong>"En préstamo"</strong> en el inventario institucional.
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEntrega = false" class="h-12 px-4 border border-borde text-slate-600 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="h-12 px-5 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Confirmar entrega</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
