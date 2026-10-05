@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-bold text-slate-900">
                Hola, {{ session('user')['name'] ?? 'usuario' }}
            </h1>
            <p class="text-sm text-slate-500">Bienvenido al sistema de Préstamo de Equipos ECCI.</p>
        </div>

        @if ($errors->has('inicio'))
            <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg">
                {{ $errors->first('inicio') }}
            </div>
        @endif

        <div class="bg-white p-6 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)]">
            <p class="text-sm text-slate-600">
                Tu módulo está en construcción. Pronto podrás consultar el catálogo de equipos
                y gestionar tus solicitudes de préstamo desde aquí.
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            <a href="{{ route('equipos.index') }}" class="bg-white p-5 rounded-xl border border-slate-200/80 hover:border-blue-300 transition text-sm font-semibold text-[#0B3D91]">
                Catálogo de equipos
            </a>
            <a href="{{ route('usuarios.index') }}" class="bg-white p-5 rounded-xl border border-slate-200/80 hover:border-blue-300 transition text-sm font-semibold text-[#0B3D91]">
                Gestión de usuarios
            </a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="w-full bg-white p-5 rounded-xl border border-slate-200/80 hover:border-blue-300 transition text-sm font-semibold text-[#0B3D91] text-left">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
@endsection
