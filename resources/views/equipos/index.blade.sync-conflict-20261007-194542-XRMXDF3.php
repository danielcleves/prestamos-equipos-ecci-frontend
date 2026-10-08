@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{ search: '', statusFilter: '' }">

    {{-- Breadcrumb y Cabecera --}}
    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
                <span>Sistema</span>
                <span>></span>
                <span class="text-slate-700 font-medium">Catálogo de equipos</span>
            </div>
            <h2 class="text-xl font-bold text-[#1F2937]">Catálogo de equipos</h2>
            <p class="text-xs text-[#6B7280]">Consulta los equipos disponibles para solicitar un préstamo.</p>
        </div>

        @if($esAdmin ?? false)
            <a href="{{ route('equipos.create') }}" class="h-12 px-5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] flex items-center gap-2 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Registrar equipo</span>
            </a>
        @endif
    </div>

    {{-- Notificación de éxito --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-[10px] flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-[10px]">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Contenedor de la Tabla/Catálogo --}}
    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        
        {{-- Barra de Filtros --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-[#D9DEE7] flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar por código, nombre o categoría..." class="w-full h-12 pl-9 pr-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
            </div>

            <select x-model="statusFilter" class="text-xs bg-white border border-[#D9DEE7] rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
                <option value="">Todos los estados</option>
                <option value="disponible">Disponible</option>
                <option value="mantenimiento">En mantenimiento</option>
                <option value="no_disponible">No disponible</option>
            </select>
        </div>

        {{-- Tabla de Equipos --}}
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Código / Serial</th>
                        <th class="py-3 px-6">Equipo</th>
                        <th class="py-3 px-6">Categoría</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6">Descripción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($equipos as $item)
                        @php
                            $equipo = is_array($item) ? $item : (array) $item;
                            $codigo = $equipo['codigo'] ?? 'S/C';
                            $nombre = $equipo['nombre'] ?? 'Sin nombre';
                            $categoria = $equipo['categoria']['nombre'] ?? ($equipo['categoria_nombre'] ?? 'General');
                            $estado = strtolower($equipo['estado'] ?? 'disponible');
                            $descripcion = $equipo['descripcion'] ?? '-';

                            $estadoBadge = match($estado) {
                                'disponible' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'mantenimiento' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default => 'bg-rose-50 text-rose-700 border-rose-200'
                            };
                            $estadoLabel = match($estado) {
                                'disponible' => 'Disponible',
                                'mantenimiento' => 'En mantenimiento',
                                default => 'No disponible'
                            };
                        @endphp
                        <tr 
                            x-show="(search === '' || 
                                    @js(strtolower($codigo)).includes(search.toLowerCase()) || 
                                    @js(strtolower($nombre)).includes(search.toLowerCase()) || 
                                    @js(strtolower($categoria)).includes(search.toLowerCase())) &&
                                    (statusFilter === '' || @js($estado) === statusFilter)"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-mono font-medium text-[#0B3D91]">{{ $codigo }}</td>
                            <td class="py-3.5 px-6 font-semibold text-slate-800">{{ $nombre }}</td>
                            <td class="py-3.5 px-6 text-slate-600">
                                <span class="px-2.5 py-1 bg-slate-100 text-slate-700 rounded-md text-[11px] font-medium border border-slate-200">
                                    {{ $categoria }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border {{ $estadoBadge }}">
                                    {{ $estadoLabel }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-500 max-w-xs truncate">{{ $descripcion }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-12 text-center text-slate-400">
                                <svg class="w-10 h-10 mx-auto text-slate-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/></svg>
                                No hay equipos registrados en el catálogo aún.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection