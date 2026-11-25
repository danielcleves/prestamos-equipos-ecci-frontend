{{--
    Avisos de la sesión compartidos por las vistas de equipos.

    `PrestamoWebController::store()` y `EquipoWebController::store()` redirigen a
    `equipos.index` (gestión o `?vista=catalogo`), así que las dos vistas tienen que
    pintar el mismo bloque; si falta en una, la acción se ejecuta pero el usuario no
    ve la confirmación ni el motivo del error (BUG-0012).
--}}
@if(session('success'))
    <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-[10px] flex items-center gap-2 shadow-sm">
        <svg class="w-4 h-4 text-emerald-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-[10px] space-y-1">
        <p class="font-semibold">{{ $errors->first() }}</p>
    </div>
@endif
