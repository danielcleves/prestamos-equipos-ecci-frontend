@extends('layouts.app')

@section('content')
    @php
        // H-15 (EVD-033): Inicio no ofrece destinos que el rol no puede usar. El
        // encargado tampoco entra a /usuarios, así que esa tarjeta es solo del admin.
        // El contenido con métricas por rol es la decisión D-2 y no se inventa aquí.
        $usuarioInicio = session('user') ?? [];
        $rolesInicio = $usuarioInicio['roles'] ?? [];
        $nombresRolesInicio = is_array($rolesInicio[0] ?? null)
            ? array_column($rolesInicio, 'name')
            : (is_array($rolesInicio) ? $rolesInicio : []);
        $esAdminInicio = in_array('admin', $nombresRolesInicio, true);
    @endphp

    <div class="space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">
                Hola, {{ session('user')['name'] ?? 'usuario' }}
            </h1>
            <p class="text-sm text-slate-500">Bienvenido al sistema de Préstamo de Equipos ECCI.</p>
        </div>

        @if ($errors->has('inicio'))
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-lg">
                {{ $errors->first('inicio') }}
            </div>
        @endif

        <div class="bg-white p-6 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)]">
            <p class="text-sm text-slate-600">
                Usa los accesos de abajo para empezar. Aquí verás el resumen de tu actividad
                cuando esté definido qué necesita cada rol.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <a href="{{ route('equipos.index') }}" class="bg-white p-5 rounded-xl border border-slate-200/80 hover:border-blue-300 transition text-sm font-semibold text-marca-700">
                Catálogo de equipos
            </a>
            @if($esAdminInicio)
                <a href="{{ route('usuarios.index') }}" class="bg-white p-5 rounded-xl border border-slate-200/80 hover:border-blue-300 transition text-sm font-semibold text-marca-700">
                    Gestión de usuarios
                </a>
            @endif
        </div>
    </div>
@endsection
