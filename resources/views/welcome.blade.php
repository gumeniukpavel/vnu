<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Кабінет здобувача ВНУ</title>

    {{-- CSRF для форми виходу --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Vite --}}
    @vite(['resources/js/app.js'])

    {{-- PWA (якщо додав) --}}
    <link rel="manifest" href="/manifest.json">
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(console.error)
        }
    </script>
</head>
<body>
<div id="app"></div>
</body>
</html>
