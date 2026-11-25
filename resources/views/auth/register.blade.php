@extends('layouts.guest')

@section('content')
<main class="min-h-screen flex items-center justify-center px-4 py-10">
    <section class="w-full max-w-md bg-white rounded-2xl shadow-2xl p-8">
        <div class="mb-6">
            <p class="text-xs font-bold uppercase tracking-wider text-marca-500 mb-1">Sistema de préstamo de equipos</p>
            <h1 class="text-2xl font-bold text-texto">Crear cuenta</h1>
            <p class="text-xs text-slate-500 mt-1">Regístrate con tu correo institucional. La cuenta queda como <strong>Solicitante</strong>.</p>
        </div>

        @if(session('success'))
            <div class="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg space-y-1">
                @foreach($errors->all() as $mensaje)
                    <p>{{ $mensaje }}</p>
                @endforeach
            </div>
        @endif

        <form action="{{ route('registro.store') }}" method="POST" class="space-y-4">
            @csrf

            <div>
                <label for="name" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">Nombre completo</label>
                <input id="name" name="name" type="text" required value="{{ old('name') }}" autocomplete="name"
                    class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label for="email" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">Correo institucional</label>
                <input id="email" name="email" type="email" required value="{{ old('email') }}" autocomplete="email" placeholder="ejemplo@ecci.edu.co"
                    class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition">
                <p class="text-xs text-slate-400 mt-1">Solo se permiten los correos o dominios autorizados por el administrador.</p>
            </div>

            <div>
                <label for="password" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">Contraseña</label>
                <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"
                    class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition">
            </div>

            <div>
                <label for="password_confirmation" class="block text-sm font-bold text-texto tracking-wider uppercase mb-1">Confirmar contraseña</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                    class="w-full h-12 px-4 bg-white border border-borde-campo rounded-[10px] text-sm focus:ring-2 focus:ring-marca-700 focus:border-transparent outline-none transition">
            </div>

            <button type="submit" class="w-full h-12 px-4 bg-marca-700 hover:bg-marca-500 text-white font-medium rounded-[10px] text-sm shadow-md shadow-blue-950/20 transition duration-150">
                Crear cuenta
            </button>
        </form>

        <p class="mt-5 text-center text-xs text-slate-500">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="text-marca-700 font-semibold hover:underline">Iniciar sesión</a>
        </p>
    </section>
</main>
@endsection
