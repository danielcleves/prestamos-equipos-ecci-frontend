@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    estadoFilter: '',
    openModalDetalle: false,
    mostrandoRechazo: false,
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
        ubicacion: ''
    },
    abrirModal(item) {
        this.selected = { ...item };
        this.mostrandoRechazo = false;
        this.motivoRechazo = '';
        this.openModalDetalle = true;
    }
}" class="max-w-7xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
            <span>Personal de préstamo</span>
            <span>></span>
            <span class="text-slate-700 font-medium">Gestión de solicitudes</span>
        </div>
        <h2 class="text-xl font-bold text-[#1F2937]">Solicitudes de préstamo</h2>
        <p class="text-xs text-[#6B7280]">Revisa las solicitudes para aprobar o rechazar el préstamo de equipos.</p>
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

    {{-- Tabla de Solicitudes --}}
    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        {{-- Filtros --}}
        <div class="p-4 bg-slate-50/50 border-b border-[#D9DEE7] flex flex-wrap gap-4 items-center justify-between">
            <div class="relative flex-1 min-w-[240px] max-w-md">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por solicitante o equipo..." class="w-full h-11 pl-9 pr-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
            </div>

            <select x-model="estadoFilter" class="text-xs bg-white border border-[#D9DEE7] rounded-[10px] px-3 h-11 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
                <option value="">Todos los estados</option>
                <option value="solicitado">Solicitado</option>
                <option value="aprobado">Aprobado</option>
                <option value="rechazado">Rechazado</option>
                <option value="entregado">Entregado</option>
                <option value="devuelto">Devuelto</option>
            </select>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">#</th>
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">F. Requerida</th>
                        <th class="py-3 px-6">F. Devolución</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($solicitudes as $item)
                        @php
                            $id = $item['id'];
                            $solic = $item['solicitante']['name'] ?? ($item['usuario']['name'] ?? 'Usuario');
                            $doc = $item['solicitante']['email'] ?? ($item['usuario']['email'] ?? 'N/D');
                            $eqNom = $item['equipo']['nombre'] ?? 'Equipo';
                            $eqCod = $item['equipo']['codigo'] ?? 'S/C';
                            $eqDisp = strtolower($item['equipo']['estado'] ?? 'disponible') === 'disponible';
                            $ub = $item['equipo']['ubicacion'] ?? 'Sala A - Bodega 1';
                            $fSol = !empty($item['fecha_solicitud']) ? date('Y-m-d', strtotime($item['fecha_solicitud'])) : date('Y-m-d');
                            $fIni = !empty($item['fecha_inicio']) ? date('Y-m-d', strtotime($item['fecha_inicio'])) : '—';
                            $fFin = !empty($item['fecha_devolucion_estimada']) ? date('Y-m-d', strtotime($item['fecha_devolucion_estimada'])) : '—';
                            $st = strtolower($item['estado'] ?? 'solicitado');

                            $itemJson = [
                                'id' => $id,
                                'solicitante' => $solic,
                                'documento' => $doc,
                                'fecha_solicitud' => $fSol,
                                'fecha_inicio' => $fIni,
                                'fecha_devolucion' => $fFin,
                                'estado' => ucfirst($st),
                                'equipo_nombre' => $eqNom,
                                'equipo_codigo' => $eqCod,
                                'equipo_disponibilidad' => $eqDisp ? 'Disponible' : 'No disponible',
                                'ubicacion' => $ub,
                            ];
                        @endphp
                        <tr 
                            x-show="(search === '' || 
                                    @js(strtolower($solic)).includes(search.toLowerCase()) || 
                                    @js(strtolower($eqNom)).includes(search.toLowerCase()) || 
                                    @js(strtolower($eqCod)).includes(search.toLowerCase())) &&
                                    (estadoFilter === '' || @js($st) === estadoFilter)"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-mono text-slate-400">#{{ $id }}</td>
                            <td class="py-3.5 px-6">
                                <p class="font-bold text-slate-800">{{ $solic }}</p>
                                <p class="text-[11px] text-slate-400">{{ $doc }}</p>
                            </td>
                            <td class="py-3.5 px-6">
                                <p class="font-semibold text-slate-700">{{ $eqNom }}</p>
                                <span class="font-mono text-slate-400 text-[11px]">{{ $eqCod }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fIni }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fFin }}</td>
                            <td class="py-3.5 px-6">
                                @if($st === 'solicitado')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                                        Pendiente
                                    </span>
                                @elseif($st === 'aprobado')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Aprobado
                                    </span>
                                @elseif($st === 'rechazado')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        Rechazado
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 text-[#1D5FD0] border border-blue-200">
                                        {{ ucfirst($st) }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right">
                                <button 
                                    type="button" 
                                    @click="abrirModal(@js($itemJson))"
                                    class="h-9 px-3.5 bg-slate-100 hover:bg-[#0B3D91] hover:text-white text-slate-700 text-xs font-semibold rounded-[10px] transition inline-flex items-center gap-1.5"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Ver detalle</span>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No hay solicitudes registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL DETALLE DE SOLICITUD (MOCKUP HU-08) --}}
    <div x-show="openModalDetalle" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalDetalle = false" class="bg-white rounded-2xl shadow-2xl border border-[#D9DEE7] w-full max-w-xl overflow-hidden">
            
            {{-- Header Modal --}}
            <div class="px-6 py-5 border-b border-[#D9DEE7] flex justify-between items-start bg-white">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Detalle de solicitud</h3>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Solicitud #<span x-text="selected.id"></span> · <span x-text="selected.estado"></span>
                    </p>
                </div>
                <button @click="openModalDetalle = false" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>

            <div class="p-6 space-y-6 text-xs">
                {{-- Grid Datos Solicitante y Fechas --}}
                <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">SOLICITANTE</span>
                        <span class="font-bold text-slate-800 text-sm mt-0.5 block" x-text="selected.solicitante"></span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">DOCUMENTO / EMAIL</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.documento"></span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">FECHA DE SOLICITUD</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_solicitud"></span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">FECHA REQUERIDA</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_inicio"></span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">FECHA DE DEVOLUCIÓN</span>
                        <span class="text-slate-700 mt-0.5 block" x-text="selected.fecha_devolucion"></span>
                    </div>

                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">ESTADO</span>
                        <span class="inline-block mt-0.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 border border-amber-200" x-text="selected.estado"></span>
                    </div>
                </div>

                {{-- Separador EQUIPO SOLICITADO --}}
                <div class="pt-4 border-t border-slate-100">
                    <div class="text-center mb-4">
                        <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider bg-white px-2">EQUIPO SOLICITADO</span>
                    </div>

                    <div class="grid grid-cols-2 gap-y-4 gap-x-6 bg-slate-50/70 p-4 rounded-xl border border-slate-100">
                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">EQUIPO</span>
                            <span class="font-bold text-slate-800 mt-0.5 block" x-text="selected.equipo_nombre"></span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">CÓDIGO</span>
                            <span class="font-mono text-slate-700 mt-0.5 block font-bold" x-text="selected.equipo_codigo"></span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">DISPONIBILIDAD</span>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold border mt-0.5"
                                  :class="selected.equipo_disponibilidad === 'Disponible' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200'">
                                <span class="w-1.5 h-1.5 rounded-full" :class="selected.equipo_disponibilidad === 'Disponible' ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                                <span x-text="selected.equipo_disponibilidad"></span>
                            </span>
                        </div>

                        <div>
                            <span class="text-[10px] uppercase font-bold text-slate-400 block tracking-wider">UBICACIÓN</span>
                            <span class="text-slate-700 mt-0.5 block" x-text="selected.ubicacion"></span>
                        </div>
                    </div>
                </div>

                {{-- Formulario para ingresar motivo de rechazo (condicional) --}}
                <div x-show="mostrandoRechazo" x-cloak class="pt-4 border-t border-slate-100 space-y-2">
                    <label class="block text-xs font-semibold text-rose-700">
                        Motivo del rechazo <span class="text-rose-500">* (Obligatorio)</span>
                    </label>
                    <textarea 
                        x-model="motivoRechazo" 
                        rows="2" 
                        placeholder="Indica la razón por la cual se rechaza la solicitud..."
                        class="w-full p-2.5 text-xs bg-white border border-rose-200 rounded-[10px] focus:outline-none focus:ring-1 focus:ring-rose-500 text-slate-700"
                    ></textarea>
                </div>

                {{-- Acciones del Modal --}}
                <div class="flex items-center justify-between pt-4 border-t border-slate-100">
                    <button type="button" @click="openModalDetalle = false" class="h-10 px-4 border border-[#D9DEE7] text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>

                    <template x-if="selected.estado === 'Solicitado' || selected.estado === 'Pendiente'">
                        <div class="flex items-center gap-2">
                            
                            {{-- Botón / Confirmación de Rechazo --}}
                            <template x-if="!mostrandoRechazo">
                                <button type="button" @click="mostrandoRechazo = true" class="h-10 px-4 border border-rose-200 bg-rose-50 hover:bg-rose-100 text-rose-700 text-xs font-semibold rounded-[10px] transition flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                    <span>Rechazar</span>
                                </button>
                            </template>

                            <template x-if="mostrandoRechazo">
                                <form :action="'{{ url('/prestamos') }}/' + selected.id + '/rechazar'" method="POST">
                                    @csrf
                                    <input type="hidden" name="motivo_rechazo" :value="motivoRechazo">
                                    <button type="submit" :disabled="!motivoRechazo.trim()" class="h-10 px-4 bg-rose-600 hover:bg-rose-700 disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-semibold rounded-[10px] transition flex items-center gap-1.5 shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                        <span>Confirmar rechazo</span>
                                    </button>
                                </form>
                            </template>

                            {{-- Botón Aprobar (HU-08 / KAN-95) --}}
                            <template x-if="selected.equipo_disponibilidad === 'Disponible' && !mostrandoRechazo">
                                <form :action="'{{ url('/prestamos') }}/' + selected.id + '/aprobar'" method="POST">
                                    @csrf
                                    <button type="submit" class="h-10 px-5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-[10px] transition shadow-sm flex items-center gap-1.5">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                        <span>Aprobar solicitud</span>
                                    </button>
                                </form>
                            </template>

                            {{-- Bloqueo si el equipo no está disponible --}}
                            <template x-if="selected.equipo_disponibilidad !== 'Disponible' && !mostrandoRechazo">
                                <button type="button" disabled title="No se puede aprobar porque el equipo no está disponible" class="h-10 px-5 bg-slate-100 text-slate-400 text-xs font-semibold rounded-[10px] cursor-not-allowed flex items-center gap-1.5">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    <span>Equipo no disponible</span>
                                </button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

        </div>
    </div>

</div>
@endsection