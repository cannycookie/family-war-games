<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBoardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('scoreboard.key');
        $given = (string) $request->route('board');

        // Constant-time compare so the key can't be teased out by timing.
        if (! hash_equals($expected, $given)) {
            abort(404);
        }

        $pin = config('scoreboard.pin');

        if (filled($pin) && ! hash_equals((string) $pin, (string) $request->cookie('board_pin'))) {
            if ($request->routeIs('board.pin*')) {
                return $next($request);
            }

            return redirect()->route('board.pin', ['board' => $given]);
        }

        return $next($request);
    }
}
