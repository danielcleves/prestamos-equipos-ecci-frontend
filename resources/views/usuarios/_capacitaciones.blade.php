{{--
    Grupo de capacitaciones por categoría de equipo (alta y edición de usuarios).

    La capacitación es por categoría: estar capacitado para un router no habilita
    un servidor. Las categorías que la exigen llevan la etiqueta «Exige capacitación».

    `capacitaciones_enviadas` solo viaja si el grupo se pinta: así el controlador
    envía siempre la lista completa (también [] al desmarcar todo) y, si no hay
    categorías que mostrar, no toca las capacitaciones guardadas.

    Variables: $categorias (lista con id, nombre, requiere_capacitacion),
    $errorCategorias (string|null) y $modo ('alta' | 'edicion').
--}}
@php
    $marcadasAlta = array_map('intval', (array) old('capacitaciones', []));
@endphp

<fieldset class="space-y-2" aria-describedby="ayuda-capacitaciones-{{ $modo }}">
    <legend class="block text-sm font-semibold text-slate-700 mb-1">Capacitaciones por categoría</legend>
    <p id="ayuda-capacitaciones-{{ $modo }}" class="text-xs text-slate-400">
        Marca las categorías en que la persona está capacitada. Sin capacitación no podrá solicitar equipos de las categorías que la exigen.
    </p>

    @if($errorCategorias ?? null)
        <p class="text-xs text-aviso-texto bg-aviso-suave border border-amber-200 rounded-[10px] px-3 py-2">{{ $errorCategorias }}</p>
    @endif

    @if($categorias === [])
        <p class="text-xs text-slate-400">No hay categorías registradas en el inventario.</p>
    @else
        <input type="hidden" name="capacitaciones_enviadas" value="1">
        <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto pr-1">
            @foreach($categorias as $categoria)
                <li>
                    <label for="capacitacion-{{ $modo }}-{{ $categoria['id'] }}" class="flex items-start gap-2.5 px-3 py-2 border border-slate-200 rounded-[10px] cursor-pointer hover:bg-slate-50 transition">
                        <input
                            id="capacitacion-{{ $modo }}-{{ $categoria['id'] }}"
                            type="checkbox"
                            name="capacitaciones[]"
                            value="{{ $categoria['id'] }}"
                            @if($modo === 'edicion')
                                x-model="editUser.capacitaciones"
                            @else
                                @checked(in_array($categoria['id'], $marcadasAlta, true))
                            @endif
                            class="mt-0.5 w-4 h-4 shrink-0 rounded border-borde-campo text-marca-700 focus:ring-marca-700"
                        >
                        <span class="min-w-0">
                            <span class="block text-sm text-slate-700 font-medium break-words">{{ $categoria['nombre'] }}</span>
                            @if($categoria['requiere_capacitacion'])
                                <span class="mt-0.5 inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold border bg-aviso-suave text-aviso-texto border-amber-200">Exige capacitación</span>
                            @endif
                        </span>
                    </label>
                </li>
            @endforeach
        </ul>
    @endif
</fieldset>
