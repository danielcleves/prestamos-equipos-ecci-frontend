@extends('layouts.app')

@section('content')
@php
    $inputClase = 'w-full h-11 px-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700 text-slate-700';
@endphp

<div x-data="{
    openModalEdit: false,
    rolEditado: { id: null, etiqueta: '', descripcion: '', cupo_prestamos: 0, es_sistema: false, es_predeterminado: false },
    editar(rol) {
        this.rolEditado = { ...rol, descripcion: rol.descripcion ?? '' };
        this.openModalEdit = true;
    }
}" class="max-w-6xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div>
        <h2 class="text-2xl font-bold text-texto">Roles y cupos de préstamo</h2>
        <p class="text-sm text-texto-suave">Crea roles para los solicitantes (estudiante, profesor, coordinador…) y define cuántos préstamos vivos puede tener cada uno (RN-02).</p>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errorConexion)
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl flex items-center gap-2 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
            <span>{{ $errorConexion }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1">
            @foreach($errors->all() as $mensaje)
                <p>{{ $mensaje }}</p>
            @endforeach
        </div>
    @endif

    {{-- Nuevo rol --}}
    <form action="{{ route('roles.store') }}" method="POST" class="bg-white p-6 rounded-xl border border-borde shadow-sm space-y-4">
        @csrf
        <div>
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Nuevo rol de solicitante</h3>
            <p class="text-xs text-slate-400 mt-0.5">Los roles nuevos solo cambian el cupo y los días extra de préstamo; no dan permisos de administración.</p>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label for="nuevo-etiqueta" class="block text-sm font-semibold text-slate-700 mb-1.5">Nombre del rol</label>
                <input id="nuevo-etiqueta" type="text" name="etiqueta" required minlength="3" maxlength="60" placeholder="Ej.: Profesor" value="{{ old('etiqueta') }}" class="{{ $inputClase }}">
            </div>
            <div>
                <label for="nuevo-cupo" class="block text-sm font-semibold text-slate-700 mb-1.5">Cupo de préstamos vivos</label>
                <input id="nuevo-cupo" type="number" name="cupo_prestamos" required min="0" max="1000" value="{{ old('cupo_prestamos', 3) }}" class="{{ $inputClase }}">
            </div>
            <div>
                <label for="nuevo-descripcion" class="block text-sm font-semibold text-slate-700 mb-1.5">Descripción <span class="text-slate-400 font-normal">(opcional)</span></label>
                <input id="nuevo-descripcion" type="text" name="descripcion" maxlength="255" value="{{ old('descripcion') }}" class="{{ $inputClase }}">
            </div>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="es_predeterminado" value="1" @checked(old('es_predeterminado')) class="rounded border-slate-300 text-marca-700 focus:ring-marca-700">
                Usar como rol predeterminado del auto-registro
            </label>
            <button type="submit" class="h-12 px-5 bg-marca-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-[10px] transition">
                Crear rol
            </button>
        </div>
    </form>

    {{-- Listado --}}
    <div class="bg-white rounded-xl border border-borde shadow-sm overflow-hidden">
        <div class="p-6 pb-3">
            <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Roles registrados</h3>
            <p class="text-xs text-slate-400 mt-0.5">Préstamos vivos = solicitados, aprobados o entregados. Con cupo 0 el rol no puede solicitar.</p>
        </div>

        <ul class="divide-y divide-slate-100">
            @forelse($roles as $rol)
                <li class="px-6 py-4 flex flex-col md:flex-row md:items-center gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-sm font-semibold text-slate-800">{{ $rol['etiqueta'] }}</span>
                            <span class="text-xs font-mono text-slate-400">{{ $rol['nombre'] }}</span>
                            @if($rol['es_sistema'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border bg-slate-100 text-slate-700 border-slate-200">Sistema</span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200/60">Solicitante</span>
                            @endif
                            @if($rol['es_predeterminado'])
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold border bg-blue-50 text-marca-700 border-blue-200/60">Predeterminado</span>
                            @endif
                        </div>
                        @if($rol['descripcion'])
                            <p class="text-xs text-slate-500 mt-1">{{ $rol['descripcion'] }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-6 text-xs">
                        <div class="text-center">
                            <p class="text-lg font-bold text-marca-700 leading-none">{{ $rol['cupo_prestamos'] }}</p>
                            <p class="text-xs text-slate-400 mt-1">cupo</p>
                        </div>
                        <div class="text-center">
                            <p class="text-lg font-bold text-slate-700 leading-none">{{ $rol['usuarios_count'] ?? '—' }}</p>
                            <p class="text-xs text-slate-400 mt-1">usuarios</p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button type="button" @click="editar(@js($rol))" class="text-slate-500 hover:text-marca-700 font-medium transition">
                                Editar
                            </button>
                            @if(! $rol['es_sistema'] && ! $rol['es_predeterminado'] && $rol['id'])
                                <form action="{{ route('roles.destroy', $rol['id']) }}" method="POST" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" onclick="return confirm(@js('¿Eliminar el rol «'.$rol['etiqueta'].'»?'))" class="text-rose-600 hover:text-rose-800 font-medium transition">
                                        Eliminar
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </li>
            @empty
                <li class="px-6 py-8 text-center text-xs text-slate-400">No hay roles para mostrar.</li>
            @endforelse
        </ul>
    </div>

    {{-- Modal de edición --}}
    <div x-show="openModalEdit" x-cloak class="fixed inset-0 m-0 z-[60] flex items-center justify-center p-4 bg-slate-900/40" @keydown.escape.window="openModalEdit = false">
        <div
            @click.outside="openModalEdit = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-editar-rol-titulo"
            x-ref="dialogoEditarRol"
            tabindex="-1"
            x-effect="openModalEdit && $nextTick(() => $refs.dialogoEditarRol.focus())"
            class="w-full max-w-md bg-white rounded-xl shadow-xl border border-borde"
        >
            <form :action="'{{ url('/roles') }}/' + rolEditado.id" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <h3 id="modal-editar-rol-titulo" class="text-sm font-bold text-slate-800">Editar rol</h3>
                    <p class="text-xs text-slate-400 mt-0.5" x-show="rolEditado.es_sistema">Rol de sistema: puedes cambiar su nombre visible y su cupo, pero no eliminarlo.</p>
                </div>
                <div>
                    <label for="editar-etiqueta" class="block text-sm font-semibold text-slate-700 mb-1.5">Nombre del rol</label>
                    <input id="editar-etiqueta" type="text" name="etiqueta" x-model="rolEditado.etiqueta" required minlength="3" maxlength="60" class="{{ $inputClase }}">
                </div>
                <div>
                    <label for="editar-cupo" class="block text-sm font-semibold text-slate-700 mb-1.5">Cupo de préstamos vivos</label>
                    <input id="editar-cupo" type="number" name="cupo_prestamos" x-model="rolEditado.cupo_prestamos" required min="0" max="1000" class="{{ $inputClase }}">
                </div>
                <div>
                    <label for="editar-descripcion" class="block text-sm font-semibold text-slate-700 mb-1.5">Descripción <span class="text-slate-400 font-normal">(opcional)</span></label>
                    <input id="editar-descripcion" type="text" name="descripcion" x-model="rolEditado.descripcion" maxlength="255" class="{{ $inputClase }}">
                </div>
                <label x-show="!rolEditado.es_sistema" class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="es_predeterminado" value="1" :checked="rolEditado.es_predeterminado" :disabled="rolEditado.es_predeterminado" class="rounded border-slate-300 text-marca-700 focus:ring-marca-700">
                    <span x-text="rolEditado.es_predeterminado ? 'Es el rol predeterminado del auto-registro' : 'Usar como rol predeterminado del auto-registro'"></span>
                </label>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEdit = false" class="h-12 px-4 bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-semibold rounded-[10px] transition">Cancelar</button>
                    <button type="submit" class="h-12 px-4 bg-marca-700 hover:bg-blue-800 text-white text-sm font-semibold rounded-[10px] transition">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
