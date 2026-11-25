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
        <h2 class="text-xl font-bold text-[#1F2937]">Entregas pendientes</h2>
        <p class="text-xs text-[#6B7280]">Solicitudes aprobadas que están listas para que el equipo sea entregado físicamente al solicitante.</p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl space-y-1">
            <p class="font-bold">{{ $errors->first() }}</p>
        </div>
    @endif

    @if(isset($errorConexion))
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    {{-- Tarjeta Métrica Superior (Mockup HU-09) --}}
    <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm flex items-center gap-4 max-w-sm">
        <div class="w-12 h-12 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 17a2 2 0 100 4 2 2 0 000-4zm10 0a2 2 0 100 4 2 2 0 000-4zM3 4h3l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
        </div>
        <div>
            <span class="text-3xl font-extrabold text-slate-800">{{ $totalPendientes }}</span>
            <p class="text-xs font-semibold text-slate-700">Pendientes de entrega</p>
            <p class="text-[11px] text-purple-600 font-medium">Solicitudes aprobadas sin entregar</p>
        </div>
    </div>

    {{-- Tabla de Equipos por Entregar --}}
    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Equipos por entregar</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $totalPendientes }} {{ $totalPendientes === 1 ? 'entrega pendiente' : 'entregas pendientes' }}</p>
            </div>
        </div>

        {{-- Buscador reactivo --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-[#D9DEE7]">
            <div class="relative max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por solicitante o equipo..." class="w-full h-11 pl-9 pr-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
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

        {{-- Escritorio: tabla compacta con la acción fija a la derecha (H-14) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                {{-- H-22: sin filas no se muestra el encabezado de columnas --}}
                @if($filasEntrega->isNotEmpty())
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-3">Solicitante</th>
                        <th class="py-3 px-3">Equipo</th>
                        <th class="py-3 px-3">F. Préstamo</th>
                        <th class="py-3 px-3">F. Devolución</th>
                        <th class="py-3 px-3">Estado</th>
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
                                    <div class="w-8 h-8 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ $fila['iniciales'] }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="font-bold text-slate-800">{{ $fila['solicitante'] }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $fila['documento'] }}</p>
                                    </div>
                                </div>
                            </td>

                            {{-- Equipo y código --}}
                            <td class="py-3.5 px-3">
                                <p class="font-semibold text-slate-700">{{ $fila['equipo'] }}</p>
                                <span class="font-mono text-[11px] text-slate-400">{{ $fila['codigo'] }}</span>
                            </td>

                            {{-- Fechas --}}
                            <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">{{ $fila['fecha_prestamo'] }}</td>
                            <td class="py-3.5 px-3 text-slate-600 whitespace-nowrap">{{ $fila['fecha_devolucion'] }}</td>

                            {{-- Estado Badge --}}
                            <td class="py-3.5 px-3">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                    <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                                    Pendiente de entrega
                                </span>
                            </td>

                            {{-- Botón Registrar entrega --}}
                            <td class="py-3.5 px-3 text-right sticky right-0 bg-white">
                                <button
                                    type="button"
                                    @click="abrirModal(@js($fila['json']))"
                                    class="h-9 px-3.5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm whitespace-nowrap"
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

        {{-- Móvil: una tarjeta por entrega (guía UI/UX §9 y §6.4) --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($filasEntrega as $fila)
                <div
                    x-show="search === '' ||
                            @js($fila['busqueda']['solicitante']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['equipo']).includes(search.toLowerCase()) ||
                            @js($fila['busqueda']['codigo']).includes(search.toLowerCase())"
                    class="p-4 space-y-3"
                >
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-full bg-emerald-500 text-white flex items-center justify-center font-bold text-[11px] shrink-0">
                            {{ $fila['iniciales'] }}
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $fila['solicitante'] }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ $fila['documento'] }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200 shrink-0">
                            <span class="w-1.5 h-1.5 rounded-full bg-purple-500"></span>
                            Pendiente
                        </span>
                    </div>

                    <dl class="grid grid-cols-2 gap-3 text-[11px]">
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
                        class="w-full h-11 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] inline-flex items-center justify-center gap-1.5 transition shadow-sm"
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
    <div x-show="openModalEntrega" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalEntrega = false" class="bg-white rounded-xl shadow-xl border border-[#D9DEE7] w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-[#D9DEE7] flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Confirmar Entrega de Equipo</h3>
                <button @click="openModalEntrega = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/prestamos') }}/' + selectedPrestamo.id + '/entrega'" method="POST" class="p-6 space-y-4">
                @csrf

                <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs space-y-1.5">
                    <p><strong class="text-slate-700">Solicitante:</strong> <span class="text-[#0B3D91] font-bold" x-text="selectedPrestamo.solicitante"></span></p>
                    <p><strong class="text-slate-700">Equipo:</strong> <span class="text-slate-800 font-semibold" x-text="selectedPrestamo.equipo + ' (' + selectedPrestamo.codigo + ')'"></span></p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Condición del equipo al entregar <span class="text-rose-500">*</span></label>
                    <select name="condicion_entrega" required class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none text-slate-700">
                        <option value="bueno" selected>Bueno (Completamente funcional)</option>
                        <option value="con_danos">Con daños menores</option>
                        <option value="requiere_mantenimiento">Requiere mantenimiento</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Observaciones de entrega <span class="text-slate-400 font-normal">(opcional)</span></label>
                    <textarea 
                        name="observaciones" 
                        rows="3" 
                        placeholder="Accesorios entregados (cargador, mouse, estuche), estado estético..."
                        class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                    ></textarea>
                </div>

                <div class="p-3 bg-amber-50 rounded-[10px] border border-amber-200 text-amber-800 text-[11px] leading-relaxed">
                    Al confirmar, el préstamo cambiará a <strong>"Entregado"</strong> y el equipo pasará al estado <strong>"En préstamo"</strong> en el inventario institucional.
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEntrega = false" class="h-10 px-4 border border-[#D9DEE7] text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="h-10 px-5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Confirmar entrega</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
