<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Membatasi akses endpoint berdasarkan peran pengguna (role:ADMIN,QS). */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Sesi kamu telah berakhir. Silakan login kembali.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Akun kamu dinonaktifkan. Hubungi Admin.'], 403);
        }

        if ($roles !== [] && ! in_array($user->role?->code, $roles, true)) {
            return response()->json(['message' => 'Kamu tidak memiliki akses ke halaman ini.'], 403);
        }

        return $next($request);
    }
}
