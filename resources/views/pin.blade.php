<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ config('scoreboard.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
</head>
<body>
    <form class="pin-page" method="POST" action="{{ route('board.pin.submit', ['board' => $board]) }}">
        @csrf
        <h1>{{ config('scoreboard.name') }}</h1>
        <p class="muted">Enter the PIN once and this phone will remember it.</p>
        <input type="password" name="pin" inputmode="numeric" pattern="[0-9]*"
               autocomplete="off" autofocus aria-label="PIN">
        @if ($error) <p class="error">{{ $error }}</p> @endif
        <button type="submit" class="btn big">Open the scoreboard</button>
    </form>
</body>
</html>
