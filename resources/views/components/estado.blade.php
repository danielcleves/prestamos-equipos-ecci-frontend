{{--
    Píldora de estado del préstamo o del equipo (guía UI/UX §15).
    El color sale de App\Support\EstadoPildora, el mismo que usa el modal de
    solicitudes, para que un estado se vea igual en todas las pantallas.

    Uso: <x-estado estado="aprobado" /> · <x-estado tipo="equipo" estado="mantenimiento" />
         <x-estado estado="aprobado" texto="Pendiente de entrega" />
--}}
@props(['estado', 'tipo' => 'prestamo', 'texto' => null])

@php
    $clases = \App\Support\EstadoPildora::clases($tipo, $estado);
    $etiqueta = $texto ?? \App\Support\EstadoPildora::etiqueta($tipo, $estado);
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border whitespace-nowrap '.$clases['pildora']]) }}>
    <span class="w-1.5 h-1.5 rounded-full shrink-0 {{ $clases['punto'] }}" aria-hidden="true"></span>
    {{ $etiqueta }}
</span>
