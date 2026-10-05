<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Equipos - Sistema de Préstamo de Equipos</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-[#F5F7FA] text-[#1F2937] min-h-screen flex flex-col">
    <header class="bg-[#0B3D91] text-white px-6 py-4 flex justify-between items-center shadow-sm">
        <h1 class="text-xl font-semibold">Sistema de Préstamo de Equipos</h1>
        <div class="flex items-center gap-4">
            <span class="text-sm text-gray-200">{{ session('user')['name'] ?? 'Usuario' }}</span>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="bg-white/10 hover:bg-white/20 text-white text-xs px-3 py-1.5 rounded-lg transition-colors">
                    Cerrar sesión
                </button>
            </form>
        </div>
    </header>

    <main class="flex-1 max-w-7xl w-full mx-auto px-6 py-8">
        <div class="bg-white rounded-xl shadow-sm border border-[#D9DEE7] p-8 text-center">
            <div class="w-16 h-16 bg-blue-50 text-[#0B3D91] rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-semibold text-[#1F2937] mb-2">Catálogo de Equipos</h2>
            <p class="text-[#6B7280] max-w-md mx-auto mb-6 text-sm">
                Módulo en construcción (HU-03). Aquí los solicitantes y docentes podrán consultar y reservar los equipos disponibles.
            </p>
            @if(in_array('admin', session('user')['roles'] ?? []))
                <a href="{{ route('usuarios.index') }}" class="inline-block bg-[#0B3D91] hover:bg-[#1D5FD0] text-white px-4 py-2.5 rounded-lg text-sm font-medium transition-colors">
                    Ir a Gestión de Usuarios
                </a>
            @endif
        </div>
    </main>
</body>
</html>