<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'peran' => \App\Http\Middleware\CekPeran::class,
            'batas.sesi' => \App\Http\Middleware\BatasSesi::class,
        ]);

        // Belum login -> ke halaman login; sudah login tapi buka /login -> ke dashboard
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('dashboard'));
        $middleware->trustProxies(at: '*');

        // Halaman setelah login tidak disimpan di cache browser (tombol Back setelah logout)
        $middleware->web(append: \App\Http\Middleware\TanpaCache::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Isian kata sandi tidak ikut disimpan ke sesi saat validasi gagal
        $exceptions->dontFlash([
            'kata_sandi_lama',
            'kata_sandi_baru',
            'kata_sandi_baru_confirmation',
        ]);
    })->create();