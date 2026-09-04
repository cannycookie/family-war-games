<!DOCTYPE html>
<html lang="en">
<head>
    @php($board = config('scoreboard.key'))
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827" media="(prefers-color-scheme: dark)">
    <meta name="theme-color" content="#f8fafc" media="(prefers-color-scheme: light)">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ config('scoreboard.name') }}">
    <title>{{ config('scoreboard.name') }}</title>
    <link rel="manifest" href="{{ route('board.manifest', ['board' => $board]) }}">
    <link rel="icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
    @livewireStyles
</head>
<body>
    {{ $slot }}

    @livewireScripts
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('{{ url('sw.js') }}').catch(() => {});
            });
        }
    </script>
</body>
</html>
