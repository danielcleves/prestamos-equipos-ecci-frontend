@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    categoryFilter: '',
    statusFilter: '',
    openModalView: false,
    openModalEdit: false,
    selectedEquipo: {
        id: null,
        codigo: '',
        nombre: '',
        categoria: '',
        estado: 'disponible',
        estado_crudo: 'disponible',
        ubicacion: '',
        descripcion: '',
        observaciones: ''
    },
    abrirVer(equipo) {
        this.selectedEquipo = { ...equipo };
        this.openModalView = true;
    },
    abrirEditar(equipo) {
        this.selectedEquipo = { ...equipo };
        this.openModalEdit = true;
    }
}" class="space-y-6 max-w-7xl mx-auto">

    {{-- Cabecera --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-texto">Gestión de equipos</h2>
            <p class="text-sm text-texto-suave">Administra el inventario de equipos tecnológicos y audiovisuales disponibles para préstamo.</p>
        </div>
    </div>

    {{-- Alertas --}}
    @include('equipos._alertas')

    @if(isset($errorConexion))
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-[10px] flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    {{-- Tarjetas de Resumen Superior --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $metricas['disponibles'] ?? 0 }}</span>
                <p class="text-xs font-semibold text-slate-700">Equipos disponibles</p>
                <p class="text-xs text-emerald-600 font-medium">Listos para préstamo</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-marca-700 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $metricas['prestados'] ?? 0 }}</span>
                <p class="text-xs font-semibold text-slate-700">Equipos prestados</p>
                <p class="text-xs text-blue-600 font-medium">Actualmente en uso</p>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-borde shadow-sm flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
            <div>
                <span class="text-3xl font-extrabold text-slate-800">{{ $metricas['mantenimiento'] ?? 0 }}</span>
                <p class="text-xs font-semibold text-slate-700">En mantenimiento</p>
                <p class="text-xs text-amber-600 font-medium">Fuera de servicio</p>
            </div>
        </div>
    </div>

    {{-- Inventario de Equipos --}}
    <div class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        
        <div class="p-6 border-b border-borde flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Inventario de equipos</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ count($equipos) }} equipos registrados en total</p>
            </div>

            @if($esAdmin ?? false)
                <a href="{{ route('equipos.create') }}" class="h-12 px-5 bg-marca-700 hover:bg-marca-500 text-white text-sm font-semibold rounded-[10px] flex items-center gap-2 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Nuevo equipo</span>
                </a>
            @endif
        </div>

        {{-- Filtros reactivos --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-borde flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar equipo por nombre o serial..." class="w-full h-12 pl-9 pr-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
            </div>

            <select x-model="categoryFilter" aria-label="Filtrar por categoría" class="text-sm bg-white border border-borde-campo rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-marca-700">
                <option value="">Filtrar por categoría</option>
                <option value="Portátil">Portátil</option>
                <option value="Tablet">Tablet</option>
                <option value="De mesa">De mesa</option>
            </select>

            <select x-model="statusFilter" aria-label="Filtrar por estado" class="text-sm bg-white border border-borde-campo rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-marca-700">
                <option value="">Filtrar por estado</option>
                <option value="disponible">Disponible</option>
                <option value="prestado">En préstamo</option>
                <option value="mantenimiento">En mantenimiento</option>
                <option value="dado_de_baja">Dado de baja</option>
            </select>
        </div>

        {{-- Tabla con los 4 estados y bloqueos para préstamo --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-borde text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Código</th>
                        <th class="py-3 px-6">Categoría</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6">Ubicación</th>
                        <th class="py-3 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($equipos as $item)
                        @php
                            $equipo = is_array($item) ? $item : (array) $item;
                            $id = $equipo['id'] ?? null;
                            $codigo = $equipo['codigo'] ?? 'S/C';
                            $nombre = $equipo['nombre'] ?? 'Sin nombre';
                            $categoria = $equipo['categoria']['nombre'] ?? ($equipo['categoria_nombre'] ?? 'General');
                            $rawEstado = strtolower($equipo['estado'] ?? 'disponible');

                            // Normalización hacia los 4 estados de la HU-04
                            $estado = match($rawEstado) {
                                'prestado', 'en préstamo', 'en_prestamo' => 'prestado',
                                'mantenimiento', 'en mantenimiento', 'en_mantenimiento' => 'mantenimiento',
                                'dado_de_baja', 'baja', 'dado de baja' => 'dado_de_baja',
                                default => 'disponible',
                            };

                            $ubicacion = $equipo['ubicacion'] ?? 'No registrada';
                            $descripcion = $equipo['descripcion'] ?? '';
                            $observaciones = $equipo['observaciones'] ?? '';

                            $equipoJson = [
                                'id' => $id,
                                'codigo' => $codigo,
                                'nombre' => $nombre,
                                'categoria' => $categoria,
                                'estado' => $estado,
                                // Valor que espera el backend (PATCH /equipos/{id}/estado)
                                'estado_crudo' => match($estado) {
                                    'prestado' => 'en_prestamo',
                                    default => $estado,
                                },
                                'ubicacion' => $ubicacion,
                                'descripcion' => $descripcion,
                                'observaciones' => $observaciones,
                            ];
                        @endphp
                        <tr 
                            x-show="(search === '' || 
                                    @js(strtolower($codigo)).includes(search.toLowerCase()) || 
                                    @js(strtolower($nombre)).includes(search.toLowerCase())) &&
                                    (categoryFilter === '' || @js($categoria) === categoryFilter) &&
                                    (statusFilter === '' || @js($estado) === statusFilter)"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-semibold text-slate-800 flex items-center gap-3">
                                <span class="w-8 h-8 rounded-lg bg-blue-50 text-marca-700 flex items-center justify-center shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                </span>
                                <div>
                                    <p>{{ $nombre }}</p>
                                    @if(in_array($estado, ['mantenimiento', 'dado_de_baja']))
                                        <span class="text-xs text-rose-600 font-semibold">● No apto para préstamo</span>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-6 font-mono text-slate-500 text-xs">{{ $codigo }}</td>
                            <td class="py-3.5 px-6 text-slate-600">{{ $categoria }}</td>
                            <td class="py-3.5 px-6">
                                <x-estado tipo="equipo" :estado="$estado" />
                            </td>
                            <td class="py-3.5 px-6 text-slate-500">{{ $ubicacion }}</td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <button 
                                    type="button" 
                                    @click="abrirVer(@js($equipoJson))"
                                    class="inline-flex items-center gap-1 text-slate-500 hover:text-marca-700 font-medium transition"
                                >
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                    <span>Ver</span>
                                </button>
                                
                                @if($esAdmin ?? false)
                                    <button 
                                        type="button" 
                                        @click="abrirEditar(@js($equipoJson))"
                                        class="inline-flex items-center gap-1 text-slate-500 hover:text-marca-700 font-medium transition"
                                    >
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                        <span>Editar</span>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-400">
                                No hay equipos registrados en el inventario.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- MODAL 1: VER DETALLE DEL EQUIPO --}}
    <div x-show="openModalView" x-cloak @keydown.escape.window="openModalView = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalView = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-detalle-equipo-titulo"
            x-ref="dialogoDetalleEquipo"
            tabindex="-1"
            x-effect="openModalView && $nextTick(() => $refs.dialogoDetalleEquipo.focus())"
            class="bg-white rounded-xl shadow-xl border border-borde w-full max-w-lg overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-borde flex justify-between items-center bg-slate-50">
                <h3 id="modal-detalle-equipo-titulo" class="font-bold text-slate-800 text-sm">Detalle del equipo</h3>
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
                        <span class="text-slate-400 block font-medium">Estado actual</span>
                        <span class="font-semibold uppercase" :class="{
                            'text-emerald-600': selectedEquipo.estado === 'disponible',
                            'text-blue-600': selectedEquipo.estado === 'prestado',
                            'text-amber-600': selectedEquipo.estado === 'mantenimiento',
                            'text-rose-600': selectedEquipo.estado === 'dado_de_baja'
                        }" x-text="selectedEquipo.estado.replace('_', ' ')"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block font-medium">Ubicación asignada</span>
                        <span class="text-slate-800" x-text="selectedEquipo.ubicacion || 'No registrada'"></span>
                    </div>
                </div>

                <div>
                    <span class="text-slate-400 block font-medium">Apto para préstamo</span>
                    <span class="inline-block mt-1 font-semibold" :class="selectedEquipo.estado === 'disponible' ? 'text-emerald-600' : 'text-rose-600'" x-text="selectedEquipo.estado === 'disponible' ? 'Sí, disponible para solicitud' : 'No apto para préstamo'"></span>
                </div>

                <div>
                    <span class="text-slate-400 block font-medium">Descripción técnica</span>
                    <p class="text-slate-600 mt-1 bg-slate-50 p-2.5 rounded-lg border border-slate-100" x-text="selectedEquipo.descripcion || 'Sin descripción detallada.'"></p>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="button" @click="openModalView = false" class="h-10 px-4 border border-borde text-slate-700 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cerrar
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MODAL 2: ACTUALIZAR ESTADO (4 ESTADOS REQUERIDOS POR HU-04) --}}
    <div x-show="openModalEdit" x-cloak @keydown.escape.window="openModalEdit = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalEdit = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-estado-equipo-titulo"
            x-ref="dialogoEstadoEquipo"
            tabindex="-1"
            x-effect="openModalEdit && $nextTick(() => $refs.dialogoEstadoEquipo.focus())"
            class="bg-white rounded-xl shadow-xl border border-borde w-full max-w-md overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-borde flex justify-between items-center bg-slate-50">
                <h3 id="modal-estado-equipo-titulo" class="font-bold text-slate-800 text-sm">Actualizar estado del equipo</h3>
                <button @click="openModalEdit = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-lg leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/equipos') }}/' + selectedEquipo.id + '/estado'" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PATCH')

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Equipo seleccionado</label>
                    <p class="text-xs font-bold text-slate-800" x-text="selectedEquipo.nombre + ' (' + selectedEquipo.codigo + ')'"></p>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">Nuevo estado <span class="text-rose-600">*</span></label>
                        <select name="estado" x-model="selectedEquipo.estado_crudo" required class="w-full h-12 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                            <option value="disponible">Disponible (Apto para préstamo)</option>
                            <option value="en_prestamo">En préstamo</option>
                            <option value="mantenimiento">En mantenimiento (No disponible para préstamo)</option>
                            <option value="dado_de_baja">Dado de baja (No puede ser prestado)</option>
                        </select>
                </div>

                <div class="p-3 bg-amber-50 rounded-[10px] border border-amber-200 text-amber-800 text-sm leading-relaxed">
                    <strong>Regla de negocio:</strong> Si el equipo pasa a <em>En mantenimiento</em> o <em>Dado de baja</em>, queda inhabilitado para ser solicitado en préstamo.
                </div>

                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEdit = false" class="h-12 px-4 border border-borde text-slate-600 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">
                        Cancelar
                    </button>
                    <button type="submit" class="h-12 px-5 bg-marca-700 text-white text-sm font-semibold rounded-[10px] hover:bg-marca-500 transition shadow-sm">
                        Guardar cambio
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
