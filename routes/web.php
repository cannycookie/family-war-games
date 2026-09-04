<?php

use App\Http\Middleware\EnsureBoardAccess;
use App\Livewire\Scoreboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// The bare domain gives nothing away.
Route::get('/', fn () => response('', 404));

Route::get('/sw.js', function () {
    return response()
        ->file(public_path('sw.js'), ['Content-Type' => 'application/javascript']);
});

Route::middleware(EnsureBoardAccess::class)->group(function () {

    Route::get('/{board}', Scoreboard::class)->name('board');

    Route::get('/{board}/manifest.webmanifest', function (string $board) {
        return response()->json([
            'name' => config('scoreboard.name'),
            'short_name' => config('scoreboard.name'),
            'description' => 'Head to head scores',
            'start_url' => url('/'.$board),
            'scope' => url('/'.$board),
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => '#0b0f19',
            'theme_color' => '#0b0f19',
            'icons' => [
                ['src' => asset('icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('icons/icon-maskable.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json');
    })->name('board.manifest');

    Route::get('/{board}/pin', function (string $board) {
        return view('pin', ['board' => $board, 'error' => session('pin_error')]);
    })->name('board.pin');

    Route::post('/{board}/pin', function (Request $request, string $board) {
        $pin = (string) config('scoreboard.pin');

        if (hash_equals($pin, (string) $request->input('pin'))) {
            return redirect()->route('board', ['board' => $board])
                ->withCookie(cookie('board_pin', $pin, 60 * 24 * 365));
        }

        return back()->with('pin_error', 'That PIN is not right.');
    })->name('board.pin.submit');
});
