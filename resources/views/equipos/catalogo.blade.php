@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    categoryFilter: '',
    onlyAvailable: false,
    openModalView: false,
    selectedEquipo: {
        id: null,
        codigo: '',
        nombre: '',
        categoria: '',
        estado: 'disponible',
        descripcion: '',
        observaciones: ''
    },
    abrirDetalle(equipo) {
        this.selectedEquipo = { ...equipo };
        this.openModalView = true;
    }
}" class="space-y-6 max-w-7xl mx-auto">

    {{-- Encabezado --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-xl font-bold text-[#1F2937]">Catálogo de equipos</h2>
            <p class="text-xs text-[#6B7280]">Consulta los equipos disponibles para solicitar en préstamo.</p>
        </div>

        @if($esAdmin ?? false)
            <a href="{{ route('equipos.index') }}" class="h-10 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold rounded-[10px] flex items-center gap-2 transition border border-slate-300">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Volver a gestión</span>
            </a>
        @endif
    </div>

    {{-- Banner Informativo --}}
    <div class="p-4 bg-blue-50/70 border border-blue-100 text-slate-700 text-xs rounded-[10px] flex items-center gap-3 shadow-sm">
        <div class="w-8 h-8 rounded-lg bg-blue-100/70 text-[#0B3D91] flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            <span class="font-bold text-[#0B3D91]">{{ $metricas['disponibles'] ?? 0 }} equipos disponibles para solicitar.</span>
            <span class="text-slate-500"> Los equipos marcados como no disponibles pueden consultarse pero no pueden solicitarse.</span>
        </div>
    </div>

    {{-- Filtros: Buscador, Categoría, Toggle Solo Disponibles y Contador --}}
    <div class="bg-white p-4 rounded-xl border border-[#D9DEE7] shadow-sm flex flex-wrap gap-4 items-center justify-between">
        <div class="flex flex-wrap flex-1 gap-3 items-center min-w-[280px]">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar equipo..." class="w-full h-11 pl-9 pr-3 text-xs bg-slate-50/60 border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
            </div>

            <select x-model="categoryFilter" class="text-xs bg-slate-50/60 border border-[#D9DEE7] rounded-[10px] px-3 h-11 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
                <option value="">Tipo de equipo</option>
                @foreach (collect($equipos)->map(fn ($e) => $e['categoria']['nombre'] ?? ($e['categoria_nombre'] ?? 'General'))->unique()->sort()->values() as $categoriaOpcion)
                    <option value="{{ $categoriaOpcion }}">{{ $categoriaOpcion }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-center gap-6">
            {{-- Toggle Solo Disponibles --}}
            <label class="flex items-center gap-2.5 cursor-pointer select-none">
                <div class="relative">
                    <input type="checkbox" x-model="onlyAvailable" class="sr-only peer">
                    <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#0B3D91]"></div>
                </div>
                <span class="text-xs font-medium text-slate-600">Solo disponibles</span>
            </label>

            <span class="text-xs font-semibold text-slate-400 border-l border-slate-200 pl-4 py-1">
                {{ count($equipos) }} equipos
            </span>
        </div>
    </div>

    {{-- Cuadrícula de Tarjetas de Equipos (Mockup HU-05) --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($equipos as $item)
            @php
                $equipo = is_array($item) ? $item : (array) $item;
                $id = $equipo['id'] ?? null;
                $codigo = $equipo['codigo'] ?? 'S/C';
                $nombre = $equipo['nombre'] ?? 'Sin nombre';
                $categoria = $equipo['categoria']['nombre'] ?? ($equipo['categoria_nombre'] ?? 'General');
                $rawEstado = strtolower($equipo['estado'] ?? 'disponible');

                $isDisponible = ($rawEstado === 'disponible');

                $equipoJson = [
                    'id' => $id,
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'categoria' => $categoria,
                    'estado' => $rawEstado,
                    'descripcion' => $equipo['descripcion'] ?? 'Sin descripción detallada.',
                    'observaciones' => $equipo['observaciones'] ?? '',
                ];
            @endphp
            <div 
                x-show="(search === '' || 
                        @js(strtolower($codigo)).includes(search.toLowerCase()) || 
                        @js(strtolower($nombre)).includes(search.toLowerCase()) ||
                        @js(strtolower($categoria)).includes(search.toLowerCase())) &&
                        (categoryFilter === '' || @js($categoria) === categoryFilter) &&
                        (!onlyAvailable || @js($isDisponible))"
                class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm flex flex-col justify-between overflow-hidden transition hover:shadow-md {{ $isDisponible ? 'border-t-4 border-t-emerald-500' : 'opacity-70 bg-slate-50/50' }}"
            >
                <div class="p-5 space-y-3">
                    {{-- Cabecera Tarjeta: Categoría y Badge Estado --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400 truncate">{{ $categoria }}</span>
                        @if($isDisponible)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Disponible
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-500 border border-slate-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                No disponible
                            </span>
                        @endif
                    </div>

                    {{-- Nombre y Características --}}
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 line-clamp-1">{{ $nombre }}</h4>
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $equipo['descripcion'] ?? 'Equipo asignado al inventario institucional.' }}</p>
                    </div>

                    {{-- Código --}}
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-400 font-mono">
                        <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-600 font-medium">{{ $codigo }}</span>
                    </div>
                </div>

                {{-- Botón de Acción --}}
                <div class="p-4 pt-0">
                    @if($isDisponible)
                        <button 
                            type="button" 
                            @click="abrirDetalle(@js($equipoJson))"
                            class="w-full h-11 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] flex items-center justify-center gap-1.5 transition shadow-sm"
                        >
                            <span>Ver detalle</span>
                        </button>
                    @else
                        <button 
                            type="button" 
                            @click="abrirDetalle(@js($equipoJson))"
                            class="w-full h-11 bg-slate-100 text-slate-400 text-xs font-medium rounded-[10px] flex items-center justify-center gap-1.5 cursor-pointer hover:bg-slate-200/80 transition"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>No disponible</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-xl border border-[#D9DEE7]">
                No hay equipos registrados en el catálogo.
            </div>
        @endforelse
    </div>

    {{-- MODAL DE DETALLE Y SOLICITUD (HU-05) --}}
    <div x-show="openModalView" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalView = false" class="bg-white rounded-xl shadow-xl border border-[#D9DEE7] w-full max-w-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-[#D9DEE7] flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Información del Equipo</h3>
                <button @click="openModalView = false" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4 text-xs">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-slate-400 block font-medium">Código / Serial</span>
                        <span class="font-mono text-slate-800 font-bold" x-text="selectedEquipo.codigo"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Categoría</span>
                        <span class="text-slate-800 font-semibold" x-text="selectedEquipo.categoria"></span>
                    </div>
                </div>

                <div>
                    <span class="text-slate-400 block font-medium">Nombre</span>
                    <span class="text-slate-800 font-bold text-sm" x-text="selectedEquipo.nombre"></span>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <span class="text-slate-400 block font-medium">Disponibilidad</span>
                        <span class="font-semibold uppercase" :class="selectedEquipo.estado === 'disponible' ? 'text-emerald-600' : 'text-rose-600'" x-text="selectedEquipo.estado === 'disponible' ? 'Disponible para préstamo' : 'No disponible'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Categoría</span>
                        <span class="text-slate-800" x-text="selectedEquipo.categoria"></span>
                    </div>
                </div>

                <div>
                    <span class="text-slate-400 block font-medium">Descripción técnica</span>
                    <p class="text-slate-600 mt-1 bg-slate-50 p-2.5 rounded-lg border border-slate-100" x-text="selectedEquipo.descripcion || 'Sin descripción detallada.'"></p>
                </div>

                <div class="flex justify-between items-center pt-3 border-t border-slate-100">
                    <button type="button" @click="openModalView = false" class="h-10 px-4 border border-[#D9DEE7] text-slate-700 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cerrar
                    </button>

                    <template x-if="selectedEquipo.estado === 'disponible'">
                        <button type="button" class="h-10 px-5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] transition shadow-sm">
                            Solicitar préstamo
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
