@extends('layouts.app')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto" x-data="{
    codigo: @js(old('codigo', '')),
    nombre: @js(old('nombre', '')),
    categoriaId: @js(old('categoria_id', ''))
}">
    {{-- Breadcrumb y Título --}}
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
            <a href="{{ route('equipos.index') }}" class="hover:text-[#0B3D91] transition flex items-center gap-1">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                Equipos
            </a>
            <span>/</span>
            <span class="text-slate-700 font-medium">Registrar equipo</span>
        </div>
        <h2 class="text-xl font-bold text-[#1F2937]">Registrar equipo</h2>
        <p class="text-xs text-[#6B7280]">Registra un nuevo equipo para mantener actualizado el catálogo</p>
    </div>

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-[10px] space-y-1">
            <p class="font-semibold">Corrige los siguientes errores antes de continuar:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('equipos.store') }}" method="POST">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            
            {{-- Columna Izquierda: Formularios de Entrada --}}
            <div class="lg:col-span-8 space-y-6">
                
                {{-- Tarjeta 1: Información básica --}}
                <div class="bg-white rounded-xl border border-[#D9DEE7] p-6 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-sm">Información básica</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Código o serial <span class="text-rose-500">*</span>
                            </label>
                            <input 
                                type="text" 
                                name="codigo" 
                                x-model="codigo"
                                placeholder="Ej. CP-011" 
                                required
                                class="w-full h-12 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Identificador único del equipo</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">
                                Tipo de equipo <span class="text-rose-500">*</span>
                            </label>
                            <select 
                                name="categoria_id" 
                                x-model="categoriaId"
                                required
                                class="w-full h-12 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none"
                            >
                                <option value="">Seleccionar tipo</option>
                                @foreach($categorias as $cat)
                                    <option value="{{ $cat['id'] }}" {{ old('categoria_id') == $cat['id'] ? 'selected' : '' }}>
                                        {{ $cat['nombre'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">
                            Nombre del equipo <span class="text-rose-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="nombre" 
                            x-model="nombre"
                            placeholder="Ej. Portátil Dell Latitude 5520" 
                            required
                            class="w-full h-12 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Descripción</label>
                        <textarea 
                            name="descripcion" 
                            rows="3" 
                            placeholder="Especificaciones técnicas, características relevantes..."
                            class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none"
                        >{{ old('descripcion') }}</textarea>
                    </div>
                </div>

                {{-- Tarjeta 2: Estado y ubicación --}}
                <div class="bg-white rounded-xl border border-[#D9DEE7] p-6 shadow-sm space-y-4">
                    <h3 class="font-bold text-slate-800 text-sm">Estado y ubicación</h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-2">
                                Estado inicial <span class="text-rose-500">*</span>
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer">
                                    <input type="radio" checked disabled class="text-[#0B3D91] focus:ring-[#0B3D91]">
                                    <span class="font-medium">Disponible</span>
                                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 font-semibold border border-emerald-200">Recomendado</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-not-allowed">
                                    <input type="radio" disabled class="text-slate-300">
                                    <span>En mantenimiento</span>
                                </label>
                                <label class="flex items-center gap-2 text-xs text-slate-400 cursor-not-allowed">
                                    <input type="radio" disabled class="text-slate-300">
                                    <span>No disponible</span>
                                </label>
                            </div>
                            <p class="text-[11px] text-slate-400 mt-2">El sistema asigna automáticamente el estado inicial al registrar.</p>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-slate-700 mb-1">Ubicación</label>
                            <input 
                                type="text" 
                                placeholder="Ej. Bodega Principal" 
                                disabled
                                class="w-full h-12 px-3 text-xs bg-slate-50 border border-[#D9DEE7] rounded-[10px] text-slate-400 cursor-not-allowed"
                            >
                            <p class="text-[11px] text-slate-400 mt-1">Sala, bodega o lugar de almacenamiento</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold text-slate-700 mb-1">Observaciones</label>
                        <textarea 
                            name="observaciones" 
                            rows="2" 
                            placeholder="Notas adicionales..."
                            class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none"
                        >{{ old('observaciones') }}</textarea>
                        <p class="text-[11px] text-slate-400 mt-1">Campo opcional</p>
                    </div>
                </div>

            </div>

            {{-- Columna Derecha: Imagen del equipo & Checklist --}}
            <div class="lg:col-span-4 space-y-6">
                
                {{-- Tarjeta Imagen del equipo --}}
                <div class="bg-white rounded-xl border border-[#D9DEE7] p-6 shadow-sm space-y-3">
                    <h3 class="font-bold text-slate-800 text-sm">Imagen del equipo</h3>
                    <div class="border-2 border-dashed border-[#D9DEE7] rounded-xl p-6 text-center bg-slate-50/50 hover:bg-slate-50 transition cursor-pointer">
                        <svg class="w-8 h-8 text-slate-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                        </svg>
                        <p class="text-xs font-semibold text-slate-700">Cargar imagen</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">PNG, JPG hasta 5 MB</p>
                    </div>
                    <p class="text-[11px] text-slate-400 text-center">La imagen ayuda a identificar visualmente el equipo</p>
                </div>

                {{-- Tarjeta Campos obligatorios dinámicos --}}
                <div class="bg-white rounded-xl border border-[#D9DEE7] p-6 shadow-sm space-y-3">
                    <h3 class="font-bold text-slate-800 text-sm">Campos obligatorios</h3>
                    <ul class="space-y-2 text-xs">
                        <li class="flex items-center gap-2" :class="codigo.trim() !== '' ? 'text-emerald-600' : 'text-slate-400'">
                            <span class="w-2 h-2 rounded-full" :class="codigo.trim() !== '' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                            <span>Código o serial</span>
                        </li>
                        <li class="flex items-center gap-2" :class="nombre.trim() !== '' ? 'text-emerald-600' : 'text-slate-400'">
                            <span class="w-2 h-2 rounded-full" :class="nombre.trim() !== '' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                            <span>Nombre del equipo</span>
                        </li>
                        <li class="flex items-center gap-2" :class="categoriaId !== '' ? 'text-emerald-600' : 'text-slate-400'">
                            <span class="w-2 h-2 rounded-full" :class="categoriaId !== '' ? 'bg-emerald-500' : 'bg-slate-300'"></span>
                            <span>Tipo de equipo</span>
                        </li>
                        <li class="flex items-center gap-2 text-emerald-600">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span>Estado inicial</span>
                        </li>
                    </ul>
                </div>

                {{-- Botones de Acción --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('equipos.index') }}" class="h-12 px-5 rounded-[10px] border border-[#D9DEE7] text-slate-600 text-xs font-semibold flex items-center justify-center hover:bg-slate-50 transition">
                        Cancelar
                    </a>
                    <button type="submit" class="h-12 px-5 rounded-[10px] bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold shadow-sm transition">
                        Registrar equipo
                    </button>
                </div>

            </div>

        </div>
    </form>
</div>
@endsection
