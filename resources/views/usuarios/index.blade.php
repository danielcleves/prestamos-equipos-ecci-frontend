@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <div>
            <p class="text-sm text-slate-500">Administra los usuarios, roles y permisos de acceso al sistema.</p>
        </div>

        {{-- 3 Tarjetas de Resumen en fila horizontal --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
            {{-- Card 1: Registrados --}}
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-3xl font-extrabold text-slate-800">10</span>
                    <p class="text-xs font-semibold text-slate-600">Usuarios registrados</p>
                    <p class="text-[11px] text-slate-400">Total en el sistema</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 text-[#0B2559] flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                </div>
            </div>

            {{-- Card 2: Activos --}}
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-3xl font-extrabold text-emerald-600">8</span>
                    <p class="text-xs font-semibold text-slate-600">Usuarios activos</p>
                    <p class="text-[11px] text-slate-400">Con acceso habilitado</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
            </div>

            {{-- Card 3: Inactivos --}}
            <div class="bg-white p-5 rounded-xl border border-slate-200/80 shadow-[0_1px_3px_rgba(0,0,0,0.05)] flex items-center justify-between">
                <div class="space-y-1">
                    <span class="text-3xl font-extrabold text-rose-500">2</span>
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
            {{-- Barra superior de la tabla: Título y Botón Nuevo Usuario --}}
            <div class="p-6 border-b border-slate-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h3 class="font-bold text-slate-900 text-base">Usuarios registrados</h3>
                    <p class="text-xs text-slate-400 mt-0.5">10 de 10 usuarios</p>
                </div>
                <button class="bg-[#0B2559] hover:bg-blue-900 text-white font-medium text-xs px-4 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    <span>Nuevo usuario</span>
                </button>
            </div>

            {{-- Barra de Filtros --}}
            <div class="px-6 py-4 bg-slate-50/50 border-b border-slate-100 flex flex-wrap gap-3 items-center">
                <div class="relative flex-1 min-w-[220px]">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <input type="text" placeholder="Buscar usuario..." class="w-full pl-9 pr-3 py-2 text-xs bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-[#0B2559]">
                </div>

                <select class="text-xs bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B2559]">
                    <option value="">Filtrar por rol</option>
                    <option value="admin">Administrador</option>
                    <option value="usuario">Usuario (Profesor)</option>
                </select>

                <select class="text-xs bg-white border border-slate-200 rounded-lg px-3 py-2 text-slate-600 focus:outline-none focus:ring-1 focus:ring-[#0B2559]">
                    <option value="">Filtrar por estado</option>
                    <option value="1">Activo</option>
                    <option value="0">Inactivo</option>
                </select>
            </div>

            {{-- Tabla con datos del Mockup --}}
            <div class="overflow-x-auto">
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
                        {{-- Fila Administrador --}}
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6 font-medium text-slate-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-full bg-blue-700 text-white flex items-center justify-center font-bold text-[10px]">AG</span>
                                <span>admin</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-700">Administrador General</td>
                            <td class="py-3.5 px-6 text-slate-500 font-mono text-[11px]">admin@ecci.edu.co</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold bg-purple-50 text-purple-700 border border-purple-200/60">
                                    Administrador
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <button class="text-slate-500 hover:text-blue-700 font-medium transition">Editar</button>
                                <button class="text-rose-600 hover:text-rose-800 font-medium transition">Desactivar</button>
                            </td>
                        </tr>

                        {{-- Fila Profesor --}}
                        <tr class="hover:bg-slate-50/80 transition">
                            <td class="py-3.5 px-6 font-medium text-slate-900 flex items-center gap-2.5">
                                <span class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-[10px]">PP</span>
                                <span>profesor</span>
                            </td>
                            <td class="py-3.5 px-6 text-slate-700">Profesor Prueba</td>
                            <td class="py-3.5 px-6 text-slate-500 font-mono text-[11px]">docente@ecci.edu.co</td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-semibold bg-blue-50 text-blue-700 border border-blue-200/60">
                                    Usuario (Docente)
                                </span>
                            </td>
                            <td class="py-3.5 px-6">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-600">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Activo
                                </span>
                            </td>
                            <td class="py-3.5 px-6 text-right space-x-2">
                                <button class="text-slate-500 hover:text-blue-700 font-medium transition">Editar</button>
                                <button class="text-rose-600 hover:text-rose-800 font-medium transition">Desactivar</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
