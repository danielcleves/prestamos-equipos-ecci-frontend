@extends('layouts.app')

@section('content')
@php
    $nivelesRestriccion = [
        0 => 'Libre (sin restricción)',
        1 => 'Requiere autorización adicional',
        2 => 'Solo personal administrativo',
    ];

    // Al volver por un error de validación se respeta lo que el admin marcó,
    // aunque el checkbox desmarcado no viaje en la petición.
    $capacitacionMarcada = session()->hasOldInput()
        ? (bool) old('requiere_capacitacion')
        : (bool) $parametros['elegibilidad.requiere_capacitacion'];
@endphp

<div x-data="{
    escala: @js(array_values(old('mora_escala', $parametros['mora.escala']))),
    agregarTramo() {
        this.escala.push({ desde: '', hasta: '', dias_sancion: '' });
    },
    quitarTramo(indice) {
        this.escala.splice(indice, 1);
    }
}" class="max-w-6xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <div class="flex items-center gap-2 text-xs text-slate-500 mb-1">
            <span>Administración</span>
            <span>></span>
            <span class="text-slate-700 font-medium">Configuración</span>
        </div>
        <h2 class="text-xl font-bold text-[#1F2937]">Configuración de reglas de negocio</h2>
        <p class="text-xs text-[#6B7280]">Parámetros de cupos, duración, mora, daños y elegibilidad. Los cambios aplican a las solicitudes nuevas.</p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(isset($errorConexion))
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-xs rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }} Se muestran los valores por defecto.</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-xl space-y-1">
            @foreach($errors->all() as $mensaje)
                <p>{{ $mensaje }}</p>
            @endforeach
        </div>
    @endif

    {{-- Formulario de parámetros agrupados --}}
    <form action="{{ route('configuracion.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        {{-- RN-02: cupos por rol (se gestionan en Roles y cupos) --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                <div>
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Cupos por rol (RN-02)</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Máximo de préstamos vivos (solicitado, aprobado o entregado) por rol. Los roles y sus cupos se gestionan en «Roles y cupos».</p>
                </div>
                <a href="{{ route('roles.index') }}" class="inline-flex items-center justify-center h-9 px-3.5 bg-slate-100 hover:bg-[#0B3D91] hover:text-white text-slate-700 text-xs font-semibold rounded-[10px] transition shrink-0">
                    Gestionar roles y cupos
                </a>
            </div>
            <ul class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach($roles as $rol)
                    <li class="flex items-center justify-between px-3 py-2.5 border border-slate-100 rounded-[10px] bg-slate-50/60">
                        <span class="text-xs font-semibold text-slate-700">{{ $rol['etiqueta'] }}</span>
                        <span class="text-xs font-bold text-[#0B3D91]">{{ $parametros['cupos.roles'][$rol['nombre']] ?? $rol['cupo_prestamos'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- RN-06: duración máxima --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Duración máxima del préstamo (RN-06)</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Días base por categoría más los días extra del rol del solicitante.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($categorias as $categoria)
                    <div>
                        <label for="duracion-cat-{{ $categoria['id'] }}" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            {{ $categoria['nombre'] }} <span class="text-slate-400 font-normal">(categoría {{ $categoria['id'] }})</span>
                        </label>
                        <input
                            id="duracion-cat-{{ $categoria['id'] }}"
                            type="number"
                            name="duracion_categorias[{{ $categoria['id'] }}]"
                            min="1"
                            max="365"
                            required
                            value="{{ old('duracion_categorias.'.$categoria['id'], $parametros['duracion.categorias'][$categoria['id']] ?? 7) }}"
                            class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                        >
                    </div>
                @endforeach
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100">
                @foreach(collect($roles)->pluck('etiqueta', 'nombre')->map(fn ($e) => 'Días extra · '.$e) as $rol => $etiqueta)
                    <div>
                        <label for="extra-{{ $rol }}" class="block text-xs font-semibold text-slate-700 mb-1.5">{{ $etiqueta }}</label>
                        <input
                            id="extra-{{ $rol }}"
                            type="number"
                            name="duracion_extra[{{ $rol }}]"
                            min="0"
                            max="365"
                            required
                            value="{{ old('duracion_extra.'.$rol, $parametros['duracion.roles_extra'][$rol] ?? 0) }}"
                            class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                        >
                    </div>
                @endforeach
            </div>
        </div>

        {{-- RN-04: mora --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Escala de mora (RN-04)</h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">Días de retraso → días de sanción. Deja «hasta» vacío para el último tramo (sin tope).</p>
                </div>
                <button type="button" @click="agregarTramo()" class="h-9 px-3.5 bg-slate-100 hover:bg-[#0B3D91] hover:text-white text-slate-700 text-xs font-semibold rounded-[10px] transition shrink-0 inline-flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Añadir tramo</span>
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                            <th class="py-2 px-3">Desde (días de retraso)</th>
                            <th class="py-2 px-3">Hasta (vacío = sin tope)</th>
                            <th class="py-2 px-3">Días de sanción</th>
                            <th class="py-2 px-3 text-right">Quitar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <template x-for="(tramo, indice) in escala" :key="indice">
                            <tr>
                                <td class="py-2 px-3">
                                    <input type="number" min="1" max="365" required x-model="tramo.desde" :name="'mora_escala[' + indice + '][desde]'" :aria-label="'Días desde del tramo ' + (indice + 1)" class="w-28 h-10 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" min="1" max="365" x-model="tramo.hasta" :name="'mora_escala[' + indice + '][hasta]'" :aria-label="'Días hasta del tramo ' + (indice + 1)" class="w-28 h-10 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700">
                                </td>
                                <td class="py-2 px-3">
                                    <input type="number" min="1" max="365" required x-model="tramo.dias_sancion" :name="'mora_escala[' + indice + '][dias_sancion]'" :aria-label="'Días de sanción del tramo ' + (indice + 1)" class="w-28 h-10 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700">
                                </td>
                                <td class="py-2 px-3 text-right">
                                    <button type="button" @click="quitarTramo(indice)" class="text-rose-600 hover:text-rose-800 font-medium">Quitar</button>
                                </td>
                            </tr>
                        </template>
                        <tr x-show="escala.length === 0" x-cloak>
                            <td colspan="4" class="py-4 text-center text-slate-400">No hay tramos definidos.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <label for="mora-dias-bloqueo" class="block text-xs font-semibold text-slate-700 mb-1.5">Umbral de bloqueo por mora (días)</label>
                    <input
                        id="mora-dias-bloqueo"
                        type="number"
                        name="mora_dias_bloqueo"
                        min="0"
                        max="365"
                        required
                        value="{{ old('mora_dias_bloqueo', $parametros['mora.dias_bloqueo']) }}"
                        class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                    >
                </div>
                <div class="flex items-end">
                    <p class="text-[11px] text-slate-400 pb-2">Un préstamo entregado con este retraso bloquea nuevas solicitudes.</p>
                </div>
            </div>
        </div>

        {{-- RN-08: daños --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Restricción por daños (RN-08)</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Días que permanece restringido el usuario tras una devolución con incidencia.</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="danos-dias-restriccion" class="block text-xs font-semibold text-slate-700 mb-1.5">Días de restricción</label>
                    <input
                        id="danos-dias-restriccion"
                        type="number"
                        name="danos_dias_restriccion"
                        min="0"
                        max="365"
                        required
                        value="{{ old('danos_dias_restriccion', $parametros['danos.dias_restriccion']) }}"
                        class="w-full h-11 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700"
                    >
                </div>
            </div>
        </div>

        {{-- Registro de solicitantes --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Registro de solicitantes</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Correos o dominios autorizados para crear una cuenta. El registro asigna siempre el rol Solicitante.</p>
            </div>
            <textarea
                name="correos_permitidos"
                rows="4"
                placeholder="@ecci.edu.co&#10;docente@otra-universidad.edu.co"
                class="w-full p-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700 font-mono"
            >{{ implode("\n", $parametros['registro.correos_permitidos']) }}</textarea>
            <p class="text-[11px] text-slate-400">Uno por línea. Usa <code>@ecci.edu.co</code> para todo un dominio o un correo completo para una persona. Si se vacía, se conserva la lista actual.</p>
        </div>

        {{-- RN-01: elegibilidad --}}
        <div class="bg-white p-6 rounded-xl border border-[#D9DEE7] shadow-sm space-y-4">
            <div>
                <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Elegibilidad (RN-01)</h3>
                <p class="text-[11px] text-slate-400 mt-0.5">Requisitos que se validan antes de registrar una solicitud.</p>
            </div>
            <label class="flex items-center gap-2.5 text-xs text-slate-700 font-medium">
                <input
                    type="checkbox"
                    name="requiere_capacitacion"
                    value="1"
                    @checked($capacitacionMarcada)
                    class="w-4 h-4 rounded border-[#D9DEE7] text-[#0B3D91] focus:ring-[#0B3D91]"
                >
                <span>Exigir capacitación registrada para solicitar equipos</span>
            </label>
        </div>

        <div class="flex justify-end gap-3">
            <a href="{{ route('inicio') }}" class="h-11 px-5 border border-[#D9DEE7] text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition flex items-center justify-center">
                Cancelar
            </a>
            <button type="submit" class="h-11 px-5 bg-[#0B3D91] hover:bg-[#1D5FD0] text-white text-xs font-semibold rounded-[10px] transition shadow-sm">
                Guardar configuración
            </button>
        </div>
    </form>

    {{-- RN-03: nivel de restricción por categoría --}}
    <div class="bg-white rounded-xl border border-[#D9DEE7] shadow-sm overflow-hidden">
        <div class="p-6 border-b border-[#D9DEE7]">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Nivel de restricción por categoría (RN-03)</h3>
            <p class="text-[11px] text-slate-400 mt-0.5">Una categoría restringida marca la solicitud con «Requiere autorización» antes de poder aprobarse.</p>
        </div>

        <div class="overflow-x-auto">
            <div class="md:hidden px-4 py-2 text-[11px] text-slate-400 bg-slate-50/60 border-b border-slate-100">
                Desliza la tabla para cambiar el nivel de restricción.
            </div>
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[#D9DEE7] text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Categoría</th>
                        <th class="py-3 px-6">Nivel actual</th>
                        <th class="py-3 px-6">Nuevo nivel</th>
                        <th class="py-3 px-6 text-right sticky right-0 bg-slate-50/70">Acción</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($categorias as $categoria)
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6">
                                <p class="font-semibold text-slate-700">{{ $categoria['nombre'] }}</p>
                                <span class="font-mono text-slate-400 text-[11px]">#{{ $categoria['id'] }}</span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-semibold border
                                    {{ $categoria['nivel_restriccion'] === 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : ($categoria['nivel_restriccion'] === 2 ? 'bg-rose-50 text-rose-700 border-rose-200' : 'bg-amber-50 text-amber-700 border-amber-200') }}">
                                    {{ $categoria['nivel_restriccion'] }} · {{ $nivelesRestriccion[$categoria['nivel_restriccion']] }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <form id="restriccion-{{ $categoria['id'] }}" action="{{ route('categorias.restriccion', ['id' => $categoria['id']]) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    <select name="nivel_restriccion" aria-label="Nivel de restricción de {{ $categoria['nombre'] }}" class="h-10 px-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91] text-slate-700">
                                        @foreach($nivelesRestriccion as $nivel => $etiqueta)
                                            <option value="{{ $nivel }}" @selected($categoria['nivel_restriccion'] === $nivel)>{{ $nivel }} · {{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="py-3.5 px-6 text-right sticky right-0 bg-white">
                                <button type="submit" form="restriccion-{{ $categoria['id'] }}" class="h-9 px-3.5 bg-slate-100 hover:bg-[#0B3D91] hover:text-white text-slate-700 text-xs font-semibold rounded-[10px] transition">
                                    Actualizar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-400">No hay categorías registradas en el inventario.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
