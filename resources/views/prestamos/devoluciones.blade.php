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
        fecha_devolucion: ''
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

    {{-- Breadcrumb y Cabecera --}}
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
            <span>Personal de préstamo</span>
            <span>></span>
            <span class="text-slate-700 font-medium">Devoluciones</span>
        </div>
        <h2 class="text-xl font-bold text-[#1F2937]">Devoluciones</h2>
        <p class="text-xs text-[#6B7280]">Registra la devolución de equipos prestados, el estado en que son devueltos y las observaciones correspondientes.</p>
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

    {{-- Tarjetas de Métricas Superiores (Mockup HU-11) --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-2xl">
        {{-- Tarjeta 1: Pendientes de devolución --}}
        <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#1D5FD0] flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $totalPendientes }}</span>
                <p class="text-xs font-semibold text-slate-700">Pendientes de devolución</p>
                <p class="text-[11px] text-[#1D5FD0] font-medium">Préstamos activos sin devolver</p>
            </div>
        </div>

        {{-- Tarjeta 2: Devoluciones registradas --}}
        <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $totalDevueltos }}</span>
                <p class="text-xs font-semibold text-slate-700">Devoluciones registradas</p>
                <p class="text-[11px] text-emerald-600 font-medium">Préstamos cerrados</p>
            </div>
        </div>
    </div>

    {{-- Tabs de Navegación --}}
    <div class="flex items-center gap-3 border-b border-slate-200">
        <button 
            type="button" 
            @click="tabActual = 'pendientes'" 
            :class="tabActual === 'pendientes' ? 'border-[#1D5FD0] text-[#1D5FD0] font-bold bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="px-4 py-2.5 text-xs border-b-2 font-medium transition flex items-center gap-2"
        >
            <span>Pendientes de devolución</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-blue-100 text-[#1D5FD0] font-bold">{{ $totalPendientes }}</span>
        </button>

        <button 
            type="button" 
            @click="tabActual = 'historial'" 
            :class="tabActual === 'historial' ? 'border-[#1D5FD0] text-[#1D5FD0] font-bold bg-white' : 'border-transparent text-slate-500 hover:text-slate-700'"
            class="px-4 py-2.5 text-xs border-b-2 font-medium transition flex items-center gap-2"
        >
            <span>Historial de devoluciones</span>
            <span class="px-2 py-0.5 rounded-full text-[10px] bg-slate-100 text-slate-600 font-bold">{{ $totalDevueltos }}</span>
        </button>
    </div>

    {{-- TAB 1: PRÉSTAMOS PENDIENTES DE DEVOLUCIÓN --}}
    <div x-show="tabActual === 'pendientes'" class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7] flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Préstamos pendientes de devolución</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $totalPendientes }} {{ $totalPendientes === 1 ? 'registro' : 'registros' }}</p>
            </div>
        </div>

        {{-- Buscador reactivo --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-[#D9DEE7]">
            <div class="relative max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por usuario o equipo..." class="w-full h-11 pl-9 pr-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
            </div>
        </div>

        {{-- Tabla de Devoluciones Pendientes --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Código</th>
                        <th class="py-3 px-6">F. Entrega</th>
                        <th class="py-3 px-6">F. Devolución Esperada</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($pendientes as $item)
                        @php
                            $solicitanteNombre = $item['solicitante']['name'] ?? ($item['usuario']['name'] ?? 'Solicitante');
                            $solicitanteEmail = $item['solicitante']['email'] ?? ($item['usuario']['email'] ?? 'N/D');
                            $equipoNombre = $item['equipo']['nombre'] ?? 'Equipo';
                            $equipoCodigo = $item['equipo']['codigo'] ?? 'S/C';
                            $fechaEntrega = !empty($item['fecha_entrega_real']) ? date('Y-m-d', strtotime($item['fecha_entrega_real'])) : (!empty($item['fecha_inicio']) ? date('Y-m-d', strtotime($item['fecha_inicio'])) : '—');
                            $fechaEsperada = !empty($item['fecha_devolucion_estimada']) ? date('Y-m-d', strtotime($item['fecha_devolucion_estimada'])) : '—';

                            $itemJson = [
                                'id' => $item['id'],
                                'solicitante' => $solicitanteNombre,
                                'equipo' => $equipoNombre,
                                'codigo' => $equipoCodigo,
                                'fecha_entrega' => $fechaEntrega,
                                'fecha_devolucion' => $fechaEsperada,
                            ];
                        @endphp
                        <tr 
                            x-show="search === '' || 
                                    @js(strtolower($solicitanteNombre)).includes(search.toLowerCase()) || 
                                    @js(strtolower($equipoNombre)).includes(search.toLowerCase()) ||
                                    @js(strtolower($equipoCodigo)).includes(search.toLowerCase())"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-[#1D5FD0] text-white flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ strtoupper(substr($solicitanteNombre, 0, 2)) }}
                                    </div>
                                    <div>
                                        <p class="font-bold text-slate-800">{{ $solicitanteNombre }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $solicitanteEmail }}</p>
                                    </div>
                                </div>
                            </td>

                            <td class="py-3.5 px-6 font-semibold text-slate-700">{{ $equipoNombre }}</td>
                            <td class="py-3.5 px-6 font-mono text-slate-500 text-[11px]">{{ $equipoCodigo }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fechaEntrega }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fechaEsperada }}</td>

                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-blue-50 text-[#1D5FD0] border border-blue-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#1D5FD0]"></span>
                                    Entregado
                                </span>
                            </td>

                            <td class="py-3.5 px-6 text-right">
                                <button 
                                    type="button" 
                                    @click="abrirModal(@js($itemJson))"
                                    class="h-9 px-4 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                    <span>Registrar devolución</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No hay préstamos activos pendientes de devolución.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- TAB 2: HISTORIAL DE DEVOLUCIONES CERRADAS --}}
    <div x-show="tabActual === 'historial'" x-cloak class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7]">
            <h3 class="font-bold text-slate-900 text-base">Historial de devoluciones cerradas</h3>
            <p class="text-xs text-slate-400 mt-0.5">Registro de equipos que ya fueron retornados.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Código</th>
                        <th class="py-3 px-6">F. Devolución Real</th>
                        <th class="py-3 px-6">Condición</th>
                        <th class="py-3 px-6">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($historial as $dev)
                        @php
                            $solic = $dev['solicitante']['name'] ?? ($dev['usuario']['name'] ?? 'Solicitante');
                            $eqNom = $dev['equipo']['nombre'] ?? 'Equipo';
                            $eqCod = $dev['equipo']['codigo'] ?? 'S/C';
                            $fDevReal = !empty($dev['fecha_devolucion_real']) ? date('Y-m-d H:i', strtotime($dev['fecha_devolucion_real'])) : '—';
                            $cond = $dev['condicion_devolucion_etiqueta'] ?? ($dev['condicion_devolucion'] ?? 'Bueno');
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6 font-bold text-slate-800">{{ $solic }}</td>
                            <td class="py-3.5 px-6 font-semibold text-slate-700">{{ $eqNom }}</td>
                            <td class="py-3.5 px-6 font-mono text-slate-500">{{ $eqCod }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fDevReal }}</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ str_contains(strtolower($cond), 'daño') || str_contains(strtolower($cond), 'mantenimiento') ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ $cond }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    Devuelto
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                Aún no hay devoluciones completadas registradas en el historial.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL PARA REGISTRAR DEVOLUCIÓN (HU-11) --}}
    <div x-show="openModalDevolucion" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalDevolucion = false" class="bg-white rounded-xl shadow-xl border border-[#D9DEE7] w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-[#D9DEE7] flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Registrar Devolución de Equipo</h3>
                <button @click="openModalDevolucion = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/prestamos') }}/' + selectedPrestamo.id + '/devolucion'" method="POST" class="p-6 space-y-4">
                @csrf

                <div class="p-3 bg-blue-50/60 rounded-xl border border-blue-100 text-xs space-y-1.5">
                    <p><strong class="text-slate-700">Solicitante:</strong> <span class="text-[#0B3D91] font-bold" x-text="selectedPrestamo.solicitante"></span></p>
                    <p><strong class="text-slate-700">Equipo:</strong> <span class="text-slate-800 font-semibold" x-text="selectedPrestamo.equipo + ' (' + selectedPrestamo.codigo + ')'"></span></p>
                    <p><strong class="text-slate-700">F. Devolución esperada:</strong> <span class="text-slate-600" x-text="selectedPrestamo.fecha_devolucion"></span></p>
                </div>

                {{-- Condición física del equipo --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">Condición del equipo al recibir <span class="text-rose-500">*</span></label>
                    <select name="condicion_devolucion" x-model="condicion" required class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none text-slate-700">
                        <option value="bueno">Bueno (Completamente funcional y sin daños)</option>
                        <option value="con_danos">Con daños (Golpes, rayones, piezas rotas)</option>
                        <option value="requiere_mantenimiento">Requiere mantenimiento técnico</option>
                    </select>
                </div>

                {{-- Observaciones (Obligatorias si no es 'bueno') --}}
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1.5">
                        Observaciones de devolución 
                        <span x-show="condicion !== 'bueno'" class="text-rose-500 font-bold">* (Obligatorio)</span>
                        <span x-show="condicion === 'bueno'" class="text-slate-400 font-normal">(opcional)</span>
                    </label>
                    <textarea 
                        name="observaciones" 
                        x-model="observaciones"
                        :required="condicion !== 'bueno'"
                        rows="3" 
                        placeholder="Detalle el estado del equipo, accesorios devueltos o novedad detectada..."
                        class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                    ></textarea>
                </div>

                {{-- Nota dinámica según la regla de negocio --}}
                <div class="p-3 rounded-[10px] text-[11px] leading-relaxed border" :class="condicion === 'bueno' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-amber-50 border-amber-200 text-amber-800'">
                    <template x-if="condicion === 'bueno'">
                        <p>Al confirmar, el préstamo quedará cerrado como <strong>"Devuelto"</strong> y el equipo volverá a estar <strong>"Disponible"</strong> en el catálogo.</p>
                    </template>
                    <template x-if="condicion !== 'bueno'">
                        <p>Al confirmar con daños o mantenimiento, el préstamo quedará como <strong>"Devuelto"</strong> y el equipo pasará a <strong>"En mantenimiento"</strong> (no volverá a disponible hasta ser reparado).</p>
                    </template>
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalDevolucion = false" class="h-10 px-4 border border-[#D9DEE7] text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="h-10 px-5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                        <span>Confirmar devolución</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
