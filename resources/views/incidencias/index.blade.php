@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">

    {{-- Encabezado --}}
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
            <span>Personal de préstamo</span>
            <span>></span>
            <span class="text-slate-700 font-medium">Incidencias</span>
        </div>
        <h2 class="text-xl font-bold text-[#1F2937]">Incidencias por daños</h2>
        <p class="text-xs text-[#6B7280]">Equipos devueltos con daños o mantenimiento. Mientras la incidencia esté abierta, el solicitante no puede pedir nuevos préstamos.</p>
    </div>

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

    @php
        $abiertas = collect($incidencias)->filter(fn ($i) => ($i['estado'] ?? '') !== 'cerrada')->count();
    @endphp

    <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm flex items-center gap-4 max-w-sm">
        <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
        </div>
        <div>
            <span class="text-3xl font-extrabold text-slate-800">{{ $abiertas }}</span>
            <p class="text-xs font-semibold text-slate-700">Incidencias abiertas</p>
            <p class="text-[11px] text-amber-600 font-medium">Restringen al solicitante</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7]">
            <h3 class="font-bold text-slate-900 text-base">Historial de incidencias</h3>
            <p class="text-xs text-slate-400 mt-0.5">Cierre la incidencia cuando el caso esté revisado para levantar la restricción.</p>
        </div>

        <div class="overflow-x-auto">
            <div class="md:hidden px-4 py-2 text-[11px] text-slate-400 bg-slate-50/60 border-b border-slate-100">
                Desliza la tabla para ver estado y acciones.
            </div>
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">#</th>
                        <th class="py-3 px-6">Solicitante</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Descripción</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6">Restringido hasta</th>
                        <th class="py-3 px-6 text-right sticky right-0 bg-slate-50/70">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($incidencias as $inc)
                        @php
                            $solicitante = $inc['solicitante']['name'] ?? 'Solicitante';
                            $equipo = $inc['equipo']['nombre'] ?? 'Equipo';
                            $codigo = $inc['equipo']['codigo'] ?? 'S/C';
                            $estado = strtolower($inc['estado'] ?? 'abierta');
                            $restringe = !empty($inc['restringe_hasta'])
                                ? \Illuminate\Support\Carbon::parse($inc['restringe_hasta'])->timezone('America/Bogota')->format('d/m/Y H:i')
                                : '—';
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6 font-mono text-slate-500">{{ $inc['id'] ?? '—' }}</td>
                            <td class="py-3.5 px-6 font-bold text-slate-800">
                                <p>{{ $solicitante }}</p>
                                <div class="md:hidden mt-1 text-[10px] font-normal text-slate-500">
                                    {{ ucfirst(str_replace('_', ' ', $estado)) }} · Restringido hasta {{ $estado === 'cerrada' ? '—' : $restringe }}
                                </div>
                            </td>
                            <td class="py-3.5 px-6">
                                <p class="font-semibold text-slate-700">{{ $equipo }}</p>
                                <p class="font-mono text-[11px] text-slate-400">{{ $codigo }}</p>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600 max-w-xs">{{ $inc['descripcion'] ?? '—' }}</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border {{ $estado === 'cerrada' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200' }}">
                                    {{ ucfirst(str_replace('_', ' ', $estado)) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $estado === 'cerrada' ? '—' : $restringe }}</td>
                            <td class="py-3.5 px-6 text-right sticky right-0 bg-white">
                                @if($estado !== 'cerrada')
                                    <form action="{{ route('incidencias.cerrar', $inc['id']) }}" method="POST" class="inline">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="h-9 px-4 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                            <span>Cerrar incidencia</span>
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] text-slate-400">Cerrada</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                No hay incidencias registradas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
