@extends('layouts.app')

@section('content')
@php
    $equipoId = $equipo['id'] ?? null;
    $codigo = $equipo['codigo'] ?? 'S/C';
    $nombre = $equipo['nombre'] ?? 'Sin nombre';
    $categoria = $equipo['categoria']['nombre'] ?? ($equipo['categoria_nombre'] ?? 'General');
    $descripcion = $equipo['descripcion'] ?? 'Sin descripción detallada.';
    $usuario = session('user') ?? [];
@endphp

<div x-data="{
    fechaInicio: '{{ old('fecha_inicio', date('Y-m-d')) }}',
    fechaDevolucion: '{{ old('fecha_devolucion', '') }}',
    motivo: @js(old('motivo', '')),
    get minDevolucion() {
        if (!this.fechaInicio) return '';
        const d = new Date(this.fechaInicio + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return d.toISOString().slice(0, 10);
    },
    get maxDevolucion() {
        if (!this.fechaInicio) return '';
        const d = new Date(this.fechaInicio + 'T00:00:00');
        d.setDate(d.getDate() + 7);
        return d.toISOString().slice(0, 10);
    },
    get duracionDias() {
        if (!this.fechaInicio || !this.fechaDevolucion) return '—';
        const start = new Date(this.fechaInicio);
        const end = new Date(this.fechaDevolucion);
        const diffTime = end - start;
        if (diffTime <= 0) return 'Fecha inválida';
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays + (diffDays === 1 ? ' día' : ' días');
    },
    formatDate(dateStr) {
        if (!dateStr) return '—';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        return dateStr;
    }
}" class="max-w-6xl mx-auto space-y-6">

    {{-- Breadcrumb y Título --}}
    <div>
        <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-[#0B3D91] transition font-medium mb-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Catálogo</span>
        </a>
        <h2 class="text-xl font-bold text-[#1F2937]">Solicitar préstamo</h2>
        <p class="text-xs text-[#6B7280]">Indica el período de préstamo para registrar formalmente tu solicitud.</p>
    </div>

    {{-- Mensajes de error de validación --}}
    @if ($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl space-y-1">
            <p class="font-bold">Por favor verifica los siguientes campos:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @forelse ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @empty
                @endforelse
            </ul>
        </div>
    @endif

    {{-- Formulario principal (Mockup HU-06) --}}
    <form action="{{ route('prestamos.store') }}" method="POST">
        @csrf
        <input type="hidden" name="equipo_id" value="{{ $equipoId }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
            
            {{-- Columna Izquierda --}}
            <div class="lg:col-span-2 space-y-6">
                
                {{-- Tarjeta 1: Equipo Seleccionado --}}
                <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 block mb-3">Equipo seleccionado</span>
                    
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 text-[#0B3D91] flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $nombre }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $categoria }}</p>
                                <p class="text-[11px] text-slate-400 mt-1">{{ $descripcion }}</p>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Disponible
                            </span>
                            <span class="block text-[11px] font-mono text-slate-400 mt-1 font-bold">{{ $codigo }}</span>
                        </div>
                    </div>
                </div>

                {{-- Tarjeta 2: Periodo de Préstamo --}}
                <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-5">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Periodo de préstamo</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Fecha de inicio <span class="text-rose-500">*</span></label>
                            <input 
                                type="date" 
                                name="fecha_inicio" 
                                x-model="fechaInicio" 
                                min="{{ date('Y-m-d') }}"
                                required 
                                class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1.5">Fecha de devolución <span class="text-rose-500">*</span></label>
                            <input 
                                type="date" 
                                name="fecha_devolucion" 
                                x-model="fechaDevolucion" 
                                :min="minDevolucion"
                                :max="maxDevolucion"
                                required 
                                class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1.5">Motivo del préstamo <span class="text-rose-500">*</span></label>
                        <textarea 
                            name="motivo" 
                            x-model="motivo" 
                            rows="4" 
                            required
                            maxlength="1000"
                            placeholder="Motivo del préstamo, propósito de uso, requerimientos especiales..."
                            class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                        ></textarea>
                    </div>
                </div>

            </div>

            {{-- Columna Derecha --}}
            <div class="space-y-6">
                
                {{-- Solicitante --}}
                <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Solicitante</span>

                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-[#1D5FD0] text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ strtoupper(substr($usuario['name'] ?? 'US', 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-slate-800 truncate">{{ $usuario['name'] ?? 'Usuario' }}</h4>
                            <p class="text-[11px] text-slate-400 truncate">{{ $usuario['email'] ?? 'usuario@ecci.edu.co' }}</p>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-slate-100 flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Usuario autenticado</span>
                    </div>
                </div>

                {{-- Resumen de Solicitud en tiempo real --}}
                <div class="bg-white p-5 rounded-xl border border-[#D9DEE7] shadow-sm space-y-3.5">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Resumen de solicitud</span>

                    <div class="space-y-2.5 text-xs">
                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Equipo</span>
                            <span class="font-mono font-bold text-slate-800">{{ $codigo }}</span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Inicio</span>
                            <span class="font-medium text-slate-800" x-text="formatDate(fechaInicio)"></span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Devolución</span>
                            <span class="font-medium text-slate-800" x-text="formatDate(fechaDevolucion)"></span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Duración</span>
                            <span class="font-medium text-slate-800" x-text="duracionDias"></span>
                        </div>

                        <div class="flex justify-between items-center pt-2.5 border-t border-slate-100">
                            <span class="text-slate-400">Estado inicial</span>
                            <span class="inline-block px-2.5 py-0.5 bg-blue-50 text-[#1D5FD0] font-semibold text-[10px] rounded-full border border-blue-200">
                                Solicitado
                            </span>
                        </div>
                    </div>
                </div>

                {{-- Botones --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="h-11 px-5 border border-[#D9DEE7] text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition flex items-center justify-center">
                        Cancelar
                    </a>

                    <button type="submit" class="h-11 px-5 bg-[#1D5FD0] hover:bg-[#184ea8] text-white text-xs font-semibold rounded-[10px] transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Registrar solicitud</span>
                    </button>
                </div>

            </div>

        </div>
    </form>

</div>
@endsection
