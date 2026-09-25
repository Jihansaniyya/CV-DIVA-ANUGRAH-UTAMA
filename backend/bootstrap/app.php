<?php

use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Seluruh error API dikembalikan sebagai JSON berbahasa Indonesia.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // Error validasi ditangani Laravel agar tetap mengembalikan daftar error per field.
            if ($e instanceof ValidationException) {
                return null;
            }

            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => 404,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            $pesanBaku = [
                401 => 'Sesi kamu telah berakhir. Silakan login kembali.',
                403 => 'Kamu tidak memiliki akses ke halaman ini.',
                404 => 'Data yang kamu cari tidak ditemukan.',
                405 => 'Metode permintaan tidak diizinkan.',
                429 => 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.',
                500 => 'Terjadi kesalahan pada server.',
            ];

            // Pesan bawaan framework (berbahasa Inggris) diganti pesan Bahasa Indonesia,
            // sedangkan pesan khusus aplikasi dipertahankan apa adanya.
            $bawaanFramework = ['', 'This action is unauthorized.', 'Unauthenticated.', 'Not Found', 'Forbidden', 'Server Error', 'Too Many Requests'];
            $pesanAsli = $e->getMessage();

            // Status 404 selalu memakai pesan baku agar nama model tidak bocor ke pengguna.
            $pesan = (in_array($status, [404, 500], true) || in_array($pesanAsli, $bawaanFramework, true))
                ? ($pesanBaku[$status] ?? 'Permintaan tidak dapat diproses.')
                : $pesanAsli;

            $payload = ['message' => $pesan];

            if (config('app.debug') && $status === 500) {
                $payload['debug'] = [
                    'exception' => $e::class,
                    'message' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ];
            }

            return response()->json($payload, $status);
        });
    })->create();
