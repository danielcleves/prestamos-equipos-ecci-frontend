@extends('layouts.app')

@section('content')
@php
    $equipoId = $equipo['id'] ?? null;
    $codigo = $equipo['codigo'] ?? 'S/C';
    $nombre = $equipo['nombre'] ?? 'Sin nombre';
    $categoria = $equipo['categoria']['nombre'] ?? ($equipo['categoria_nombre'] ?? 'General');
    $descripcion = $equipo['descripcion'] ?? 'Sin descripción detallada.';
    $usuario = session('user') ?? [];

    // RN-06: límite que expone el detalle del equipo; 7 días si no viene.
    $maxDias = (int) ($duracionMaxima ?? ($equipo['duracion_maxima_dias'] ?? 7));
    if ($maxDias < 1) {
        $maxDias = 7;
    }

    // KAN-195: Mapear errores del backend a campos del formulario.
    // MessageBag::first() devuelve '' (no null) cuando la clave no existe, así que
    // el respaldo a la variante *_estimada necesita ?: y no ??.
    $erroresCampo = [
        'fecha_inicio'     => $errors->first('fecha_inicio') ?: $errors->first('fecha_inicio_estimada'),
        'fecha_devolucion' => $errors->first('fecha_devolucion') ?: $errors->first('fecha_devolucion_estimada'),
        'motivo'           => $errors->first('motivo'),
        'prestamo_error'   => $errors->first('prestamo_error'),
        // KAN-188: el backend reporta la indisponibilidad del equipo bajo la clave
        // `equipo_id` (SolicitudPrestamoService) y el formulario no tiene un input
        // para ese campo, así que se muestra en el aviso de indisponibilidad.
        'disponibilidad'   => $errors->first('equipo_id'),
    ];

    // RN-01/RN-02/RN-04: el backend rechaza por elegibilidad (sanción activa,
    // cupo superado, préstamo vencido, capacitación o restricción de categoría)
    // bajo `usuario_id`. Se muestran todos los motivos.
    $motivosElegibilidad = array_values(array_filter(array_merge(
        $errors->get('elegibilidad'),
        $errors->get('usuario_id'),
        $errors->get('user_id'),
    )));

    // Mensajes que no se muestran en ningún campo: se listan en el bloque general.
    $mensajesMostrados = array_merge(array_filter($erroresCampo), $motivosElegibilidad);
    $erroresGenerales = array_values(array_filter(
        $errors->all(),
        fn (string $mensaje) => ! in_array($mensaje, $mensajesMostrados, true)
    ));
    $hayErroresGenerales = $erroresGenerales !== [];
@endphp

<div x-data="{
    fechaInicio: '{{ old('fecha_inicio', date('Y-m-d')) }}',
    fechaDevolucion: '{{ old('fecha_devolucion', '') }}',
    motivo: @js(old('motivo', '')),
    errorFechaInicio: '',
    errorFechaDevolucion: '',
    errorMotivo: '',
    hoy: '{{ date('Y-m-d') }}',
    maxDias: {{ $maxDias }},
    get minDevolucion() {
        if (!this.fechaInicio) return '';
        const d = new Date(this.fechaInicio + 'T00:00:00');
        d.setDate(d.getDate() + 1);
        return d.toISOString().slice(0, 10);
    },
    get maxDevolucion() {
        if (!this.fechaInicio) return '';
        const d = new Date(this.fechaInicio + 'T00:00:00');
        d.setDate(d.getDate() + this.maxDias);
        return d.toISOString().slice(0, 10);
    },
    get duracionDias() {
        if (!this.fechaInicio || !this.fechaDevolucion) return '—';
        const start = new Date(this.fechaInicio);
        const end = new Date(this.fechaDevolucion);
        const diffTime = end - start;
        if (diffTime <= 0) return 'Fecha inválida';
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        return diffDays + (diffDays === 1 ? ' día' : ' días');
    },
    formatDate(dateStr) {
        if (!dateStr) return '—';
        const parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '/' + parts[1] + '/' + parts[0];
        }
        return dateStr;
    },
    validarFechaInicio() {
        if (!this.fechaInicio) {
            this.errorFechaInicio = 'La fecha de inicio es requerida.';
            return false;
        }
        if (this.fechaInicio < this.hoy) {
            this.errorFechaInicio = 'La fecha de inicio no puede ser anterior al día de hoy.';
            return false;
        }
        this.errorFechaInicio = '';
        return true;
    },
    validarFechaDevolucion() {
        if (!this.fechaDevolucion) {
            this.errorFechaDevolucion = 'La fecha de devolución es requerida.';
            return false;
        }
        if (this.fechaInicio && this.fechaDevolucion <= this.fechaInicio) {
            this.errorFechaDevolucion = 'La fecha de devolución debe ser posterior a la fecha de inicio.';
            return false;
        }
        if (this.maxDevolucion && this.fechaDevolucion > this.maxDevolucion) {
            this.errorFechaDevolucion = 'La fecha de devolución no puede superar los ' + this.maxDias + ' días posteriores a la fecha de inicio.';
            return false;
        }
        this.errorFechaDevolucion = '';
        return true;
    },
    validarMotivo() {
        if (!this.motivo || this.motivo.trim().length === 0) {
            this.errorMotivo = 'El motivo del préstamo es obligatorio.';
            return false;
        }
        this.errorMotivo = '';
        return true;
    },
    validarYEnviar(event) {
        const v1 = this.validarFechaInicio();
        const v2 = this.validarFechaDevolucion();
        const v3 = this.validarMotivo();
        if (!v1 || !v2 || !v3) {
            event.preventDefault();
        }
    }
}" class="max-w-6xl mx-auto space-y-6">

    {{-- Breadcrumb y Título --}}
    <div>
        <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="inline-flex items-center gap-1.5 text-xs text-slate-500 hover:text-marca-700 transition font-medium mb-1.5">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
            <span>Catálogo</span>
        </a>
        <h2 class="text-2xl font-bold text-texto">Solicitar préstamo</h2>
        <p class="text-sm text-texto-suave">Indica el período de préstamo para registrar formalmente tu solicitud.</p>
    </div>

    {{-- KAN-188: Mensajes de disponibilidad del backend por campo --}}
    @if($erroresCampo['disponibilidad'])
        <div class="p-4 bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl flex items-start gap-3 shadow-sm">
            <svg class="w-4 h-4 text-amber-600 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            <div>
                <p class="font-semibold mb-0.5">El equipo no puede ser solicitado</p>
                <p>{{ $erroresCampo['disponibilidad'] }}</p>
            </div>
        </div>
    @endif

    {{-- RN-01/RN-02/RN-04: rechazo por elegibilidad (sanción, cupo, capacitación,
         préstamo vencido o categoría restringida) --}}
    @if($motivosElegibilidad !== [])
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1 shadow-sm">
            <p class="font-bold">No puedes solicitar este equipo todavía:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($motivosElegibilidad as $motivo)
                    <li>{{ $motivo }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- KAN-195: Mensajes de error de validación por campo --}}
    @if($erroresCampo['prestamo_error'])
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1">
            <p class="font-bold">{{ $erroresCampo['prestamo_error'] }}</p>
        </div>
    @endif

    {{-- Otros errores no mapeados --}}
    @if($hayErroresGenerales)
        <div class="p-4 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-xl space-y-1">
            <p class="font-bold">Por favor verifica los siguientes campos:</p>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($erroresGenerales as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Formulario principal (HU-06) --}}
    <form action="{{ route('prestamos.store') }}" method="POST" @submit="validarYEnviar($event)">
        @csrf
        <input type="hidden" name="equipo_id" value="{{ $equipoId }}">
        {{-- RN-06: el backend confirma el límite con la categoría y el rol --}}
        <input type="hidden" name="duracion_maxima_dias" value="{{ $maxDias }}">

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

            {{-- Columna Izquierda --}}
            <div class="lg:col-span-2 space-y-6">

                {{-- Tarjeta 1: Equipo Seleccionado --}}
                <div class="bg-white p-5 rounded-xl border border-borde shadow-sm">
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-600 block mb-3">Equipo seleccionado</span>

                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3.5">
                            <div class="w-10 h-10 rounded-lg bg-blue-50 text-marca-700 flex items-center justify-center shrink-0 mt-0.5">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-800">{{ $nombre }}</h3>
                                <p class="text-xs text-slate-500 mt-0.5">{{ $categoria }}</p>
                                <p class="text-xs text-slate-400 mt-1">{{ $descripcion }}</p>
                            </div>
                        </div>

                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                Disponible
                            </span>
                            <span class="block text-xs font-mono text-slate-400 mt-1 font-bold">{{ $codigo }}</span>
                        </div>
                    </div>
                </div>

                {{-- Tarjeta 2: Periodo de Préstamo --}}
                <div class="bg-white p-6 rounded-xl border border-borde shadow-sm space-y-5">
                    <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Periodo de préstamo</h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        {{-- Fecha de inicio con validación inline (KAN-194 / KAN-195) --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Fecha de inicio <span class="text-rose-600">*</span></label>
                            <input
                                type="date"
                                name="fecha_inicio"
                                x-model="fechaInicio"
                                @change="validarFechaInicio(); fechaDevolucion && validarFechaDevolucion()"
                                min="{{ date('Y-m-d') }}"
                                required
                                :class="errorFechaInicio ? 'border-rose-400 focus:ring-rose-400' : 'border-borde-campo focus:ring-marca-700'"
                                class="w-full h-11 px-3 text-sm bg-white border rounded-[10px] focus:outline-none focus:ring-1 text-slate-700"
                            >
                            {{-- Error PHP (vuelta del servidor) --}}
                            @if($erroresCampo['fecha_inicio'])
                                <p class="text-xs text-rose-600 mt-1">{{ $erroresCampo['fecha_inicio'] }}</p>
                            @endif
                            {{-- Error JS (validación cliente) --}}
                            <p x-show="errorFechaInicio" x-text="errorFechaInicio" class="text-xs text-rose-600 mt-1" x-cloak></p>
                        </div>

                        {{-- Fecha de devolución con validación inline (KAN-194 / KAN-195) --}}
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1.5">Fecha de devolución <span class="text-rose-600">*</span></label>
                            <input
                                type="date"
                                name="fecha_devolucion"
                                x-model="fechaDevolucion"
                                @change="validarFechaDevolucion()"
                                :min="minDevolucion"
                                :max="maxDevolucion"
                                required
                                :class="errorFechaDevolucion ? 'border-rose-400 focus:ring-rose-400' : 'border-borde-campo focus:ring-marca-700'"
                                class="w-full h-11 px-3 text-sm bg-white border rounded-[10px] focus:outline-none focus:ring-1 text-slate-700"
                            >
                            @if($erroresCampo['fecha_devolucion'])
                                <p class="text-xs text-rose-600 mt-1">{{ $erroresCampo['fecha_devolucion'] }}</p>
                            @endif
                            <p x-show="errorFechaDevolucion" x-text="errorFechaDevolucion" class="text-xs text-rose-600 mt-1" x-cloak></p>
                        </div>
                    </div>

                    {{-- Motivo con validación inline (KAN-194 / KAN-195) --}}
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1.5">Motivo del préstamo <span class="text-rose-600">*</span></label>
                        <textarea
                            name="motivo"
                            x-model="motivo"
                            @input="motivo.trim().length > 0 && (errorMotivo = '')"
                            @blur="validarMotivo()"
                            rows="4"
                            required
                            maxlength="1000"
                            placeholder="Motivo del préstamo, propósito de uso, requerimientos especiales..."
                            :class="errorMotivo ? 'border-rose-400 focus:ring-rose-400' : 'border-borde-campo focus:ring-marca-700'"
                            class="w-full p-3 text-xs bg-white border rounded-[10px] focus:outline-none focus:ring-1 text-slate-700"
                        ></textarea>
                        @if($erroresCampo['motivo'])
                            <p class="text-xs text-rose-600 mt-1">{{ $erroresCampo['motivo'] }}</p>
                        @endif
                        <p x-show="errorMotivo" x-text="errorMotivo" class="text-xs text-rose-600 mt-1" x-cloak></p>
                    </div>
                </div>

            </div>

            {{-- Columna Derecha --}}
            <div class="space-y-6">

                {{-- Solicitante --}}
                <div class="bg-white p-5 rounded-xl border border-borde shadow-sm space-y-4">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Solicitante</span>

                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-marca-500 text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ strtoupper(substr($usuario['name'] ?? 'US', 0, 2)) }}
                        </div>
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-slate-800 truncate">{{ $usuario['name'] ?? 'Usuario' }}</h4>
                            <p class="text-xs text-slate-400 truncate">{{ $usuario['email'] ?? 'usuario@ecci.edu.co' }}</p>
                        </div>
                    </div>
                </div>

                {{-- Resumen de Solicitud en tiempo real --}}
                <div class="bg-white p-5 rounded-xl border border-borde shadow-sm space-y-3.5">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-400 block">Resumen de solicitud</span>

                    <div class="space-y-2.5 text-sm">
                        <div class="flex justify-between items-center gap-3 text-slate-600">
                            <span class="text-slate-400">Equipo</span>
                            {{-- H-23: el resumen muestra el nombre del equipo, no el serial. --}}
                            <span class="text-right font-semibold text-slate-800">
                                {{ $nombre }}
                                <span class="block font-mono text-xs text-slate-500">{{ $codigo }}</span>
                            </span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Inicio</span>
                            <span class="font-medium text-slate-800" x-text="formatDate(fechaInicio)"></span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Devolución</span>
                            <span class="font-medium text-slate-800" x-text="formatDate(fechaDevolucion)"></span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Duración</span>
                            <span class="font-medium text-slate-800" x-text="duracionDias"></span>
                        </div>

                        <div class="flex justify-between items-center text-slate-600">
                            <span class="text-slate-400">Máximo permitido</span>
                            <span class="font-medium text-slate-800" x-text="maxDias + (maxDias === 1 ? ' día' : ' días')"></span>
                        </div>

                        <div class="flex justify-between items-center pt-2.5 border-t border-slate-100">
                            <span class="text-slate-400">Estado inicial</span>
                            <x-estado estado="solicitado" />
                        </div>
                    </div>
                </div>

                {{-- Botones --}}
                <div class="flex items-center justify-end gap-3 pt-2">
                    <a href="{{ route('equipos.index', ['vista' => 'catalogo']) }}" class="h-12 px-5 border border-borde text-slate-600 text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition flex items-center justify-center">
                        Cancelar
                    </a>

                    <button type="submit" class="h-12 px-5 bg-marca-500 hover:bg-marca-700 text-white text-sm font-semibold rounded-[10px] transition shadow-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>Registrar solicitud</span>
                    </button>
                </div>

            </div>

        </div>
    </form>

</div>
@endsection
