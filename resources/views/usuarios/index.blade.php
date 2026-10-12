@extends('layouts.app')

@section('content')
<div x-data="{
    search: '',
    roleFilter: '',
    statusFilter: '',
    openModalCreate: false,
    openModalEdit: false,
    editUser: { id: null, name: '', email: '', role: 'usuario' },
    openEdit(user) {
        this.editUser = { ...user };
        this.openModalEdit = true;
    }
}" class="space-y-6">

    <div>
        <p class="text-sm text-slate-500">Administra los usuarios, roles y permisos de acceso al sistema.</p>
    </div>

    {{-- Notificaciones de éxito o error --}}
    @if(session('success'))
        <div class="p-3 bg-emerald-50 border border-emerald-200 text-emerald-700 text-xs rounded-lg">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-3 bg-rose-50 border border-rose-200 text-rose-700 text-xs rounded-lg">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- 3 Tarjetas de Resumen en fila horizontal --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3 md:gap-5">
        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-slate-800">{{ $total }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios registrados</p>
                <p class="text-[11px] text-slate-400">Total en el sistema</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0B3D91] flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-emerald-600">{{ $activos }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios activos</p>
                <p class="text-[11px] text-slate-400">Con acceso habilitado</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>

        <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
            <div class="space-y-1">
                <span class="text-3xl font-extrabold text-rose-500">{{ $inactivos }}</span>
                <p class="text-xs font-semibold text-slate-600">Usuarios inactivos</p>
                <p class="text-[11px] text-slate-400">Acceso deshabilitado</p>
            </div>
            <div class="w-12 h-12 rounded-xl bg-rose-50 text-rose-500 flex items-center justify-center">
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
            <button @click="openModalCreate = true" class="bg-[#0B3D91] hover:bg-[#1D5FD0] text-white font-medium text-xs h-12 px-4 rounded-[10px] flex items-center gap-2 transition shadow-sm">
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
                <input x-model="search" type="text" placeholder="Buscar usuario..." class="w-full h-12 pl-9 pr-3 text-xs bg-white border border-[#D9DEE7] rounded-[10px] focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
            </div>

            <select x-model="roleFilter" class="text-xs bg-white border border-[#D9DEE7] rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
                <option value="">Filtrar por rol</option>
                <option value="admin">Administrador</option>
                <option value="encargado">Personal de préstamo</option>
                <option value="usuario">Solicitante</option>
            </select>

            <select x-model="statusFilter" class="text-xs bg-white border border-[#D9DEE7] rounded-[10px] px-3 h-12 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B3D91]">
                <option value="">Filtrar por estado</option>
                <option value="1">Activo</option>
                <option value="0">Inactivo</option>
            </select>
        </div>

        {{-- Tabla Dinámica (desktop/tablet ≥768 px) --}}
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-slate-100 text-[11px] font-bold uppercase tracking-wider text-slate-400 bg-slate-50/70">
                        <th class="py-3 px-6">Usuario</th>
                        <th class="py-3 px-6">Nombre Completo</th>
                        <th class="py-3 px-6">Correo Electrónico</th>
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
                            $rol = !empty($user['roles']) ? (is_array($user['roles'][0]) ? ($user['roles'][0]['name'] ?? 'usuario') : $user['roles'][0]) : ($user['role'] ?? 'usuario');
                            $rolLabel = match($rol) {
                                'admin' => 'Administrador',
                                'encargado' => 'Personal de préstamo',
                                default => 'Solicitante'
                            };
                            $rolColor = match($rol) {
                                'admin' => 'bg-purple-50 text-purple-700 border-purple-200/60',
                                'encargado' => 'bg-indigo-50 text-indigo-700 border-indigo-200/60',
                                default => 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
                            };
                            $avatarColor = match($rol) {
                                'admin' => 'bg-[#0B3D91]',
                                'encargado' => 'bg-indigo-600',
                                default => 'bg-emerald-600'
                            };
                        @endphp
                        <tr
                            x-show="(search === '' || @js(strtolower($user['name'])).includes(search.toLowerCase()) || @js(strtolower($user['email'])).includes(search.toLowerCase())) &&
                                    (roleFilter === '' || @js($rol) === roleFilter) &&
                                    (statusFilter === '' || @js((string) (int) $user['is_active']) === statusFilter)"
                            class="hover:bg-slate-50/80 transition"
                        >
                            <td class="py-3.5 px-6 font-medium text-slate-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-full {{ $avatarColor }} text-white flex items-center justify-center font-bold text-[10px]">
                                    {{ $iniciales }}
                                </span>
                                <span>{{ $username }}</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-700 font-medium">{{ $user['name'] }}</td>
                            <td class="py-3.5 px-6 text-slate-500 font-mono text-[11px]">{{ $user['email'] }}</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border {{ $rolColor }}">
                                    {{ $rolLabel }}
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                @if($user['is_active'])
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-rose-500">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactivo
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <button
                                    type="button"
                                    @click="openEdit(@js(['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $rol]))"
                                    class="text-slate-500 hover:text-[#0B3D91] font-medium transition"
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
                    $rol = !empty($user['roles']) ? (is_array($user['roles'][0]) ? ($user['roles'][0]['name'] ?? 'usuario') : $user['roles'][0]) : ($user['role'] ?? 'usuario');
                    $rolLabel = match($rol) {
                        'admin' => 'Administrador',
                        'encargado' => 'Personal de préstamo',
                        default => 'Solicitante'
                    };
                    $rolColor = match($rol) {
                        'admin' => 'bg-purple-50 text-purple-700 border-purple-200/60',
                        'encargado' => 'bg-indigo-50 text-indigo-700 border-indigo-200/60',
                        default => 'bg-emerald-50 text-emerald-700 border-emerald-200/60'
                    };
                    $avatarColor = match($rol) {
                        'admin' => 'bg-[#0B3D91]',
                        'encargado' => 'bg-indigo-600',
                        default => 'bg-emerald-600'
                    };
                @endphp
                <div
                    x-show="(search === '' || @js(strtolower($user['name'])).includes(search.toLowerCase()) || @js(strtolower($user['email'])).includes(search.toLowerCase())) &&
                            (roleFilter === '' || @js($rol) === roleFilter) &&
                            (statusFilter === '' || @js((string) (int) $user['is_active']) === statusFilter)"
                    class="p-4 space-y-3"
                >
                    <div class="flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full {{ $avatarColor }} text-white flex items-center justify-center font-bold text-[11px] shrink-0">
                            {{ $iniciales }}
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate">{{ $user['name'] }}</p>
                            <p class="text-[11px] text-slate-500 truncate">{{ $user['email'] }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold border {{ $rolColor }}">
                            {{ $rolLabel }}
                        </span>
                        @if($user['is_active'])
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-rose-500">
                                <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span> Inactivo
                            </span>
                        @endif
                    </div>

                    <div class="flex gap-2">
                        <button
                            type="button"
                            @click="openEdit(@js(['id' => $user['id'], 'name' => $user['name'], 'email' => $user['email'], 'role' => $rol]))"
                            class="flex-1 h-11 rounded-[10px] border border-[#D9DEE7] bg-white text-xs font-semibold text-[#0B3D91] hover:bg-slate-50 transition"
                        >
                            Editar
                        </button>

                        <form action="{{ route('usuarios.toggle', ['id' => $user['id'], 'accion' => $user['is_active'] ? 'desactivar' : 'activar']) }}" method="POST" class="flex-1">
                            @csrf
                            @method('PATCH')
                            <button
                                type="submit"
                                @if($user['is_active']) onclick="return confirm('¿Desactivar a {{ $user['name'] }}? Perderá su acceso al sistema.')" @endif
                                class="w-full h-11 rounded-[10px] border text-xs font-semibold transition {{ $user['is_active'] ? 'border-rose-200 text-rose-600 hover:bg-rose-50' : 'border-emerald-200 text-emerald-600 hover:bg-emerald-50' }}"
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
    <div x-show="openModalCreate" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalCreate = false" class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Registrar Nuevo Usuario</h3>
                <button @click="openModalCreate = false" class="text-slate-400 hover:text-slate-600 text-base leading-none">&times;</button>
            </div>
            <form action="{{ route('usuarios.store') }}" method="POST" class="p-6 space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Completo</label>
                    <input type="text" name="name" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Correo Electrónico (@ecci.edu.co)</label>
                    <input type="email" name="email" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rol en el Sistema</label>
                    <select name="role" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none bg-white">
                        <option value="usuario">Solicitante</option>
                        <option value="encargado">Personal de préstamo</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Contraseña Inicial</label>
                    <input type="password" name="password" required minlength="8" class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalCreate = false" class="h-12 px-4 border border-[#D9DEE7] text-[#0B3D91] text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="h-12 px-5 bg-[#0B3D91] text-white text-xs font-semibold rounded-[10px] hover:bg-[#1D5FD0] transition">Guardar Usuario</button>
                </div>
            </form>
        </div>
    </div>

    {{-- MODAL EDITAR USUARIO --}}
    <div x-show="openModalEdit" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
        <div @click.away="openModalEdit = false" class="bg-white rounded-xl shadow-xl border border-slate-200 w-full max-w-md overflow-hidden">
            <div class="px-6 py-4 border-b border-slate-100 flex justify-between items-center bg-slate-50">
                <h3 class="font-bold text-slate-800 text-sm">Editar Usuario</h3>
                <button @click="openModalEdit = false" class="text-slate-400 hover:text-slate-600 text-base leading-none">&times;</button>
            </div>

            <form :action="'{{ url('/usuarios') }}/' + editUser.id" method="POST" class="p-6 space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nombre Completo</label>
                    <input type="text" name="name" x-model="editUser.name" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Correo Electrónico</label>
                    <input type="email" name="email" x-model="editUser.email" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Rol</label>
                    <select name="role" x-model="editUser.role" required class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none bg-white">
                        <option value="usuario">Solicitante</option>
                        <option value="encargado">Personal de préstamo</option>
                        <option value="admin">Administrador</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 mb-1">Nueva Contraseña (opcional)</label>
                    <input type="password" name="password" minlength="8" placeholder="Dejar en blanco para no cambiarla" class="w-full h-12 text-xs px-3 border border-[#D9DEE7] rounded-[10px] focus:ring-1 focus:ring-[#0B3D91] outline-none">
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="openModalEdit = false" class="h-12 px-4 border border-[#D9DEE7] text-[#0B3D91] text-xs font-semibold rounded-[10px] hover:bg-slate-50 transition">Cancelar</button>
                    <button type="submit" class="h-12 px-5 bg-[#0B3D91] text-white text-xs font-semibold rounded-[10px] hover:bg-[#1D5FD0] transition">Actualizar</button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection
