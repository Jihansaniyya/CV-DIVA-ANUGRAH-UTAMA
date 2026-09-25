<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::with('role')->where('username', $request->string('username'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'Nama pengguna atau kata sandi salah.',
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'username' => 'Akun kamu dinonaktifkan. Hubungi Admin.',
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken('web-'.$user->username)->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil.',
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()->load('role')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()->currentAccessToken();

        // Token transient muncul pada sesi pengujian; hanya token personal yang perlu dihapus.
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        return response()->json(['message' => 'Kamu telah keluar dari aplikasi.']);
    }
}
