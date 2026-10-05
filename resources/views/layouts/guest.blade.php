<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Iniciar Sesión - Préstamo de Equipos ECCI' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#0B2559] min-h-screen text-slate-800 antialiased selection:bg-blue-600 selection:text-white">
    @yield('content')
</body>
</html>
