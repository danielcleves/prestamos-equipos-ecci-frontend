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
            <h2 class="text-2xl font-bold text-texto">Catálogo de equipos</h2>
            <p class="text-sm text-texto-suave">Consulta los equipos disponibles para solicitar en préstamo.</p>
        </div>

        @if($esAdmin ?? false)
            <a href="{{ route('equipos.index') }}" class="h-10 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-[10px] flex items-center gap-2 transition border border-slate-300">
                <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                <span>Volver a gestión</span>
            </a>
        @endif
    </div>

    {{-- Alertas de la solicitud y de los errores de validación (BUG-0012) --}}
    @include('equipos._alertas')

    {{-- Banner de error de carga --}}
    @if(isset($errorConexion))
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-[10px] flex items-center gap-3 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    {{-- Banner Informativo --}}
    <div class="p-4 bg-blue-50/70 border border-blue-100 text-slate-700 text-xs rounded-[10px] flex items-center gap-3 shadow-sm">
        <div class="w-8 h-8 rounded-lg bg-blue-100/70 text-marca-700 flex items-center justify-center shrink-0">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
            {{-- H-17: concordancia en singular y plural --}}
            <span class="font-bold text-marca-700">{{ $metricas['disponibles'] ?? 0 }} {{ ($metricas['disponibles'] ?? 0) === 1 ? 'equipo disponible' : 'equipos disponibles' }} para solicitar.</span>
            <span class="text-slate-500"> Los equipos marcados como no disponibles pueden consultarse pero no pueden solicitarse.</span>
        </div>
    </div>

    {{-- Filtros: Buscador, Categoría, Toggle Solo Disponibles y Contador --}}
    <div class="bg-white p-4 rounded-xl border border-borde shadow-sm flex flex-wrap gap-4 items-center justify-between">
        <div class="flex flex-wrap flex-1 gap-3 items-center min-w-[280px]">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar equipo..." class="w-full h-11 pl-9 pr-3 text-sm bg-slate-50/60 border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
            </div>

            <select x-model="categoryFilter" aria-label="Tipo de equipo" class="text-sm bg-slate-50/60 border border-borde-campo rounded-[10px] px-3 h-11 text-slate-600 focus:outline-none focus:ring-1 focus:ring-marca-700">
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
                    <div class="w-10 h-5 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-marca-700"></div>
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
                class="bg-white rounded-xl border border-borde shadow-sm flex flex-col justify-between overflow-hidden transition hover:shadow-md {{ $isDisponible ? 'border-t-4 border-t-emerald-500' : 'opacity-70 bg-slate-50/50' }}"
            >
                <div class="p-5 space-y-3">
                    {{-- Cabecera Tarjeta: Categoría y Badge Estado --}}
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 truncate">{{ $categoria }}</span>
                        @if($isDisponible)
                            <x-estado tipo="equipo" estado="disponible" />
                        @else
                            <x-estado tipo="equipo" estado="no_disponible" />
                        @endif
                    </div>

                    {{-- Nombre y Características --}}
                    <div>
                        <h4 class="text-sm font-bold text-slate-800 line-clamp-1">{{ $nombre }}</h4>
                        <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $equipo['descripcion'] ?? 'Equipo asignado al inventario institucional.' }}</p>
                    </div>

                    {{-- Código --}}
                    <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-xs text-slate-400 font-mono">
                        <span class="px-2 py-0.5 bg-slate-100 rounded text-slate-600 font-medium">{{ $codigo }}</span>
                    </div>
                </div>

                {{-- Botón de Acción --}}
                <div class="p-4 pt-0">
                    @if($isDisponible)
                        <button 
                            type="button" 
                            @click="abrirDetalle(@js($equipoJson))"
                            class="w-full h-12 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] flex items-center justify-center gap-1.5 transition shadow-sm"
                        >
                            <span>Ver detalle</span>
                        </button>
                    @else
                        <button 
                            type="button" 
                            @click="abrirDetalle(@js($equipoJson))"
                            class="w-full h-12 bg-slate-100 text-slate-400 text-sm font-medium rounded-[10px] flex items-center justify-center gap-1.5 cursor-pointer hover:bg-slate-200/80 transition"
                        >
                            <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            <span>No disponible</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full py-12 text-center text-slate-400 bg-white rounded-xl border border-borde">
                No hay equipos registrados en el catálogo.
            </div>
        @endforelse
    </div>

    {{-- MODAL DE DETALLE Y SOLICITUD (HU-05) --}}
    <div x-show="openModalView" x-cloak @keydown.escape.window="openModalView = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalView = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-info-equipo-titulo"
            x-ref="dialogoInfoEquipo"
            tabindex="-1"
            x-effect="openModalView && $nextTick(() => $refs.dialogoInfoEquipo.focus())"
            class="bg-white rounded-xl shadow-xl border border-borde w-full max-w-lg overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-borde flex justify-between items-center bg-slate-50">
                <h3 id="modal-info-equipo-titulo" class="font-bold text-slate-800 text-sm">Información del equipo</h3>
                <button @click="openModalView = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>
            <div class="p-6 space-y-4 text-sm">
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
                    <button type="button" @click="openModalView = false" class="h-10 px-4 border border-borde text-slate-700 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cerrar
                    </button>

                    <template x-if="selectedEquipo.estado === 'disponible'">
                        <a :href="'{{ url('/prestamos/solicitar') }}/' + selectedEquipo.id" class="h-12 px-5 bg-marca-500 hover:bg-marca-700 text-white text-sm font-semibold rounded-[10px] transition shadow-sm flex items-center justify-center">
                            Solicitar préstamo
                        </a>
                    </template>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
