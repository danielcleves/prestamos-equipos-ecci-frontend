@extends('layouts.app')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">

    {{-- Cabecera --}}
    <div>
        <h2 class="text-xl font-bold text-[#1F2937]">Mis préstamos vigentes</h2>
        <p class="text-xs text-[#6B7280]">Consulta los equipos que actualmente tienes en préstamo.</p>
    </div>

    {{-- Error de conexión (KAN-120) --}}
    @if(isset($errorConexion))
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-rose-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl space-y-1">
            <p class="font-bold">{{ $errors->first() }}</p>
        </div>
    @endif

    {{-- Tabla de Préstamos Activos --}}
    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7]">
            <h3 class="font-bold text-slate-900 text-base">Préstamos activos</h3>
            <p class="text-xs text-slate-400 mt-0.5">{{ count($prestamos) }} {{ count($prestamos) === 1 ? 'préstamo vigente' : 'préstamos vigentes' }}</p>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                {{-- H-22: sin filas no se muestra el encabezado de columnas --}}
                @if(count($prestamos) > 0)
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">#</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">F. Entrega</th>
                        <th class="py-3 px-6">F. Devolución Máx.</th>
                        <th class="py-3 px-6">Estado</th>
                    </tr>
                </thead>
                @endif
                <tbody class="divide-y divide-slate-100">
                    @forelse($prestamos as $item)
                        @php
                            $equipoNombre = $item['equipo']['nombre'] ?? 'Equipo';
                            $equipoCodigo = $item['equipo']['codigo'] ?? 'S/C';
                            $equipoCategoria = $item['equipo']['categoria']['nombre'] ?? 'N/D';

                            // Fecha de entrega real, con fallback a fecha_inicio (KAN-121)
                            $fechaEntregaRaw = $item['fecha_entrega_real'] ?? $item['fecha_inicio'] ?? null;
                            $fechaDevolucionRaw = $item['fecha_devolucion_estimada'] ?? null;

                            $fechaEntrega = $fechaEntregaRaw
                                ? \Carbon\Carbon::parse($fechaEntregaRaw)->timezone('America/Bogota')->format('d/m/Y H:i')
                                : '—';

                            $fechaDevolucion = $fechaDevolucionRaw
                                ? \Carbon\Carbon::parse($fechaDevolucionRaw)->timezone('America/Bogota')->format('d/m/Y H:i')
                                : '—';

                            // KAN-121: solo un préstamo ya entregado puede estar retrasado;
                            // una solicitud o un préstamo aprobado sin entregar tiene la
                            // fecha estimada en el pasado por antigüedad, no por mora.
                            $estado = strtolower($item['estado'] ?? '');
                            $estaRetrasado = false;
                            if ($estado === 'entregado' && $fechaDevolucionRaw) {
                                $devCarbon = \Carbon\Carbon::parse($fechaDevolucionRaw)->timezone('America/Bogota');
                                $estaRetrasado = $devCarbon->isPast();
                            }
                        @endphp
                        <tr class="hover:bg-slate-50/80 transition {{ $estaRetrasado ? 'bg-rose-50/30' : '' }}">
                            <td class="py-3.5 px-6 font-mono text-slate-400">#{{ $item['id'] ?? '—' }}</td>
                            <td class="py-3.5 px-6">
                                <p class="font-semibold text-slate-700">{{ $equipoNombre }}</p>
                                <span class="font-mono text-slate-400 text-[11px]">{{ $equipoCodigo }}</span>
                                <span class="text-slate-400 text-[11px] ml-1">· {{ $equipoCategoria }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $fechaEntrega }}</td>

                            {{-- Fecha devolución máxima con alerta visual si está retrasado (KAN-121) --}}
                            <td class="py-3.5 px-6">
                                @if($estaRetrasado)
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-rose-700">
                                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        {{ $fechaDevolucion }}
                                    </span>
                                    <p class="text-[10px] text-rose-600 mt-0.5">Devolución vencida</p>
                                @else
                                    <span class="text-slate-600">{{ $fechaDevolucion }}</span>
                                @endif
                            </td>

                            <td class="py-3.5 px-6">
                                @if($estaRetrasado)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        Retrasado
                                    </span>
                                @elseif($estado === 'entregado')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-blue-50 text-[#1D5FD0] border border-blue-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#1D5FD0]"></span>
                                        Vigente
                                    </span>
                                @elseif($estado === 'aprobado')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        Aprobado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                        {{ ucfirst($estado) }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-12 text-center">
                                <div class="flex flex-col items-center gap-3 text-slate-400">
                                    <svg class="w-10 h-10 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    <p class="text-sm font-medium text-slate-500">No tienes préstamos vigentes</p>
                                    <p class="text-xs text-slate-400 max-w-xs">Puedes solicitar un equipo desde el catálogo.</p>
                                    <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="mt-1 h-9 px-4 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] inline-flex items-center gap-1.5 transition shadow-sm">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                                        Ver catálogo
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Leyenda de estados (KAN-121) --}}
    @if(count($prestamos) > 0)
        <div class="flex items-center gap-6 text-[11px] text-slate-500 px-1">
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-[#1D5FD0] inline-block"></span>
                Vigente — dentro del plazo
            </span>
            <span class="flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-rose-500 inline-block"></span>
                Retrasado — fecha de devolución vencida
            </span>
        </div>
    @endif

</div>
@endsection
