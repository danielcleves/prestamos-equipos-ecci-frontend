@extends('layouts.app')

@section('content')
@php
    // RN-02: etiquetas de los roles del backend (incluye los personalizados).
    $roles = $roles ?? \App\Http\Controllers\Concerns\CatalogoRoles::rolesPorDefecto();
    $etiquetasRol = collect($roles)->pluck('etiqueta', 'nombre')->all();
    $rolPredeterminado = collect($roles)->firstWhere('es_predeterminado', true)['nombre']
        ?? ($roles[0]['nombre'] ?? 'estudiante');
@endphp
<div x-data="{
    search: '',
    roleFilter: '',
    statusFilter: '',
    openModalCreate: false,
    openModalEdit: false,
    editUser: { id: null, name: '', email: '', role: @js($rolPredeterminado), capacitado: true },
    openEdit(user) {
        this.editUser = { ...user };
        this.openModalEdit = true;
    }
}" class="space-y-6">

    <div>
        <h2 class="text-2xl font-bold text-texto">Gestión de usuarios</h2>
        <p class="text-sm text-slate-500">Administra los usuarios, roles y permisos de acceso al sistema.</p>
    </div>

    {{-- Notificaciones de éxito o error --}}
    @if(session('success'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-sm rounded-lg">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- 3 Tarjetas de Resumen en fila horizontal --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-5">
        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-slate-800">{{ $total }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios registrados</p>
                <p class="text-xs text-slate-400">Total en el sistema</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-marca-700 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-emerald-600">{{ $activos }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios activos</p>
                <p class="text-xs text-slate-400">Con acceso habilitado</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-rose-600">{{ $inactivos }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios inactivos</p>
                <p class="text-xs text-slate-400">Acceso deshabilitado</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
    </div>

    {{-- Contenedor de la Tabla Principal --}}
    <div class="bg-white rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] overflow-hidden">
        <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <div>
                <h3 class="font-bold text-slate-900 text-base">Usuarios registrados</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $total }} usuarios en total</p>
            </div>
            <button @click="openModalCreate = true" class="bg-marca-700 hover:bg-marca-500 text-white font-medium text-sm h-12 px-4 rounded-[10px] flex items-center gap-2 transition shadow-sm">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Nuevo usuario</span>
            </button>
        </div>

        {{-- Barra de Filtros con Alpine --}}
        <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex flex-wrap gap-3 items-center">
            <div class="relative flex-1 min-w-[220px]">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </span>
                <input x-model="search" type="text" placeholder="Buscar usuario..." class="w-full h-12 pl-9 pr-3 text-sm bg-white border border-borde-campo rounded-[10px] focus:outline-none focus:ring-1 focus:ring-marca-700">
            </div>

            <select x-model="roleFilter" aria-label="Filtrar por rol" class="text-sm bg-white border border-borde-campo rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-marca-700">
                <option value="">Filtrar por rol</option>
                @foreach($roles as $opcionRol)
                    <option value="{{ $opcionRol['nombre'] }}">{{ $opcionRol['etiqueta'] }}</option>
                @endforeach
            </select>

            <select x-model="statusFilter" aria-label="Filtrar por estado" class="text-sm bg-white border border-borde-campo rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-marca-700">
                <option value="">Filtrar por estado</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>

        {{-- Tabla Dinámica (desktop/tablet ≥768 px) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr class="border-b border-slate-100 text-xs font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Usuario</th>
                        <th class="py-3 px-6">Nombre completo</th>
                        <th class="py-3 px-6">Correo electrónico</th>
                        <th class="py-3 px-6">Rol</th>
                        <th class="py-3 px-6">Estado</th>
                        <th class="py-3 px-6 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($usuarios as $user)
                        @php
                            $emailParts = explode('@', $user['email']);
                            $username = $emailParts[0];
                            $nameWords = explode(' ', trim($user['name']));
                            $iniciales = strtoupper(substr($nameWords[0], 0, 1) . (isset($nameWords[1]) ? substr($nameWords[1], 0, 1) : ''));
                            $rol = $user['rol_principal'] ?? (!empty($user['roles']) ? (is_array($user['roles'][0]) ? ($user['roles'][0]['name'] ?? $rolPredeterminado) : $user['roles'][0]) : ($user['role'] ?? $rolPredeterminado));
                            $rolLabel = $etiquetasRol[$rol] ?? ($user['rol_etiqueta'] ?? ucfirst(str_replace('_', ' ', $rol)));
                            $rolColor = match($rol) {
                                'admin' => 'bg-marca-50 text-marca-700 border-blue-200',
                                'encargado' => 'bg-aviso-suave text-aviso-texto border-amber-200',
                                default => 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
                            };
                            $avatarColor = match($rol) {
                                'admin' => 'bg-marca-700',
                                'encargado' => 'bg-amber-700',
                                default => 'bg-emerald-600'
                            };
                            // RN-01: la capacitación del usuario decide si puede solicitar equipos.
                            $capacitado = (bool) ($user['capacitado'] ?? true);
                        @endphp
                        <tr
                            x-show="(search === '' || @js(strtolower($user['name'])).includes(search.toLowerCase()) || @js(strtolower($user['email'])).includes(search.toLowerCase())) &&
                                    (roleFilter === '' || @js($rol) === roleFilter) &&
                                    (statusFilter === '' || @js((string) (int) $user['is_active']) === statusFilter)"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-medium text-slate-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-full {{ $avatarColor }} text-white flex items-center justify-center font-bold text-xs">
                                    {{ $iniciales }}
                                </span>
                                <span>{{ $username }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-700 font-medium">{{ $user['name'] }}</td>
                            <td class="py-3.5 px-6 text-slate-500 font-mono text-xs">{{ $user['email'] }}</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $rolColor }}">
                                    {{ $rolLabel }}
                                </span>
                                <span class="mt-1 inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border {{ $capacitado ? 'bg-emerald-50 text-emerald-700 border-emerald-200/60' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                                    {{ $capacitado ? 'Capacitado' : 'Sin capacitación' }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                @if($user['is_active'])
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <button
                                    type="button"
                                    @click="openEdit(@js(['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $rol, 'capacitado' => (bool) ($user['capacitado'] ?? true)]))"
                                    class="text-slate-500 hover:text-marca-700 font-medium transition"
                                >
                                    Editar
                                </button>

                                <form action="{{ route('usuarios.toggle', ['id' => $user['id'], 'accion' => $user['is_active'] ? 'desactivar' : 'activar']) }}" method="POST" class="inline">
                                    @csrf
                                    @method('PATCH')
                                    <button
                                        type="submit"
                                        @if($user['is_active']) onclick="return confirm('¿Desactivar a {{ $user['name'] }}? Perderá su acceso al sistema.')" @endif
                                        class="{{ $user['is_active'] ? 'text-rose-600 hover:text-rose-800' : 'text-emerald-600 hover:text-emerald-800' }} font-medium transition"
                                    >
                                        {{ $user['is_active'] ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-400">No hay usuarios registrados en el sistema.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Listado en tarjetas para móvil (≤767 px, guía UI/UX: la tabla se convierte en tarjetas) --}}
        <div class="md:hidden divide-y divide-slate-100">
            @forelse($usuarios as $user)
                @php
                    $emailParts = explode('@', $user['email']);
                    $username = $emailParts[0];
                    $nameWords = explode(' ', trim($user['name']));
                    $iniciales = strtoupper(substr($nameWords[0], 0, 1) . (isset($nameWords[1]) ? substr($nameWords[1], 0, 1) : ''));
                    $rol = $user['rol_principal'] ?? (!empty($user['roles']) ? (is_array($user['roles'][0]) ? ($user['roles'][0]['name'] ?? $rolPredeterminado) : $user['roles'][0]) : ($user['role'] ?? $rolPredeterminado));
                    $rolLabel = $etiquetasRol[$rol] ?? ($user['rol_etiqueta'] ?? ucfirst(str_replace('_', ' ', $rol)));
                    $rolColor = match($rol) {
                        'admin' => 'bg-marca-50 text-marca-700 border-blue-200',
                        'encargado' => 'bg-aviso-suave text-aviso-texto border-amber-200',
                        default => 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
                    };
                    $avatarColor = match($rol) {
                        'admin' => 'bg-marca-700',
                        'encargado' => 'bg-amber-700',
                        default => 'bg-emerald-600'
                    };
                    // RN-01: la capacitación del usuario decide si puede solicitar equipos.
                    $capacitado = (bool) ($user['capacitado'] ?? true);
                @endphp
                <div
                    x-show="(search === '' || @js(strtolower($user['name'])).includes(search.toLowerCase()) || @js(strtolower($user['email'])).includes(search.toLowerCase())) &&
                            (roleFilter === '' || @js($rol) === roleFilter) &&
                            (statusFilter === '' || @js((string) (int) $user['is_active']) === statusFilter)"
                    class="p-4 space-y-3"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full {{ $avatarColor }} text-white flex items-center justify-center font-bold text-xs shrink-0">
                            {{ $iniciales }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $user['name'] }}</p>
                            <p class="text-xs text-slate-500 truncate">{{ $user['email'] }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold border {{ $rolColor }}">
                            {{ $rolLabel }}
                        </span>
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium border {{ $capacitado ? 'bg-emerald-50 text-emerald-700 border-emerald-200/60' : 'bg-slate-50 text-slate-500 border-slate-200' }}">
                            {{ $capacitado ? 'Capacitado' : 'Sin capacitación' }}
                        </span>
                        @if($user['is_active'])
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-rose-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactivo
                            </span>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="openEdit(@js(['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $rol, 'capacitado' => (bool) ($user['capacitado'] ?? true)]))"
                            class="flex-1 h-12 rounded-[10px] border border-borde bg-white text-xs font-semibold text-marca-700 hover:bg-slate-50 transition"
                        >
                            Editar
                        </button>

                        <form action="{{ route('usuarios.toggle', ['id' => $user['id'], 'accion' => $user['is_active'] ? 'desactivar' : 'activar']) }}" method="POST" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                @if($user['is_active']) onclick="return confirm('¿Desactivar a {{ $user['name'] }}? Perderá su acceso al sistema.')" @endif
                                class="w-full h-12 rounded-[10px] border text-sm font-semibold transition {{ $user['is_active'] ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }}"
                            >
                                {{ $user['is_active'] ? 'Desactivar' : 'Activar' }}
                            </button>
                        </form>
                    </div>
                </div>
            @empty
                <p class="py-8 text-center text-slate-400 text-xs">No hay usuarios registrados en el sistema.</p>
            @endforelse
        </div>
    </div>

    {{-- MODAL CREAR USUARIO --}}
    <div x-show="openModalCreate" x-cloak @keydown.escape.window="openModalCreate = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalCreate = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-nuevo-usuario-titulo"
            x-ref="dialogoNuevoUsuario"
            tabindex="-1"
            x-effect="openModalCreate && $nextTick(() => $refs.dialogoNuevoUsuario.focus())"
            class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 id="modal-nuevo-usuario-titulo" class="font-bold text-slate-800 text-sm">Registrar nuevo usuario</h3>
                <button @click="openModalCreate = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-base leading-none">&times;</button>
            </div>
            <form action="{{ route('usuarios.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre completo</label>
                    <input type="text" name="name" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Correo Electrónico (@ecci.edu.co)</label>
                    <input type="email" name="email" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Rol en el sistema</label>
                    <select name="role" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none bg-white">
                        @foreach($roles as $opcionRol)
                            <option value="{{ $opcionRol['nombre'] }}" @selected(old('role', $rolPredeterminado) === $opcionRol['nombre'])>{{ $opcionRol['etiqueta'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Contraseña inicial</label>
                    <input type="password" name="password" required minlength="8" class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    {{-- RN-01: la capacitación habilita solicitar equipos cuando el parámetro lo exige. --}}
                    <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="capacitado" value="1" checked class="w-4 h-4 rounded border-borde-campo text-marca-700 focus:ring-marca-700">
                        <span>Capacitado para usar los equipos</span>
                    </label>
                    <p class="text-xs text-slate-400 mt-1">Si se exige capacitación, un usuario no capacitado no puede solicitar préstamos.</p>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalCreate = false" class="h-12 px-4 border border-borde text-marca-700 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="h-12 px-5 bg-marca-700 text-white text-sm font-semibold rounded-[10px] hover:bg-marca-500 transition">Guardar usuario</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDITAR USUARIO --}}
    <div x-show="openModalEdit" x-cloak @keydown.escape.window="openModalEdit = false" class="fixed inset-0 z-[60] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div
            @click.away="openModalEdit = false"
            role="dialog"
            aria-modal="true"
            aria-labelledby="modal-editar-usuario-titulo"
            x-ref="dialogoEditarUsuario"
            tabindex="-1"
            x-effect="openModalEdit && $nextTick(() => $refs.dialogoEditarUsuario.focus())"
            class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden"
        >
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 id="modal-editar-usuario-titulo" class="font-bold text-slate-800 text-sm">Editar usuario</h3>
                <button @click="openModalEdit = false" aria-label="Cerrar" class="text-slate-400 hover:text-slate-600 text-base leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/usuarios') }}/' + editUser.id" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nombre completo</label>
                    <input type="text" name="name" x-model="editUser.name" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" x-model="editUser.email" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Rol</label>
                    <select name="role" x-model="editUser.role" required class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none bg-white">
                        @foreach($roles as $opcionRol)
                            <option value="{{ $opcionRol['nombre'] }}">{{ $opcionRol['etiqueta'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Nueva contraseña (opcional)</label>
                    <input type="password" name="password" minlength="8" placeholder="Dejar en blanco para no cambiarla" class="w-full h-12 text-sm px-3 border border-borde-campo rounded-[10px] focus:ring-1 focus:ring-marca-700 outline-none">
                </div>
                <div>
                    {{-- RN-01: al desmarcarlo, el backend (PUT /usuarios/{id}) lo guarda como no capacitado. --}}
                    <label class="flex items-center gap-2.5 text-sm font-semibold text-slate-700">
                        <input type="checkbox" name="capacitado" value="1" x-model="editUser.capacitado" class="w-4 h-4 rounded border-borde-campo text-marca-700 focus:ring-marca-700">
                        <span>Capacitado para usar los equipos</span>
                    </label>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEdit = false" class="h-12 px-4 border border-borde text-marca-700 text-sm font-semibold rounded-[10px] hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="h-12 px-5 bg-marca-700 text-white text-sm font-semibold rounded-[10px] hover:bg-marca-500 transition">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
