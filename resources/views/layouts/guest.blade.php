<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Iniciar Sesión - Préstamo de Equipos ECCI' }}</title>
    {{-- Tipografía Inter (guía UI/UX), con fallback al system-ui --}}
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0B3D91] min-h-screen text-slate-800 antialiased selection:bg-blue-600 selection:text-white">
    @yield('content')
</body>
</html>
