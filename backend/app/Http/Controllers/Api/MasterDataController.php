<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UnitResource;
use App\Models\Role;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Master data yang dipakai lintas modul: peran pengguna dan satuan pekerjaan. */
class MasterDataController extends Controller
{
    public function roles(): JsonResponse
    {
        return response()->json(['data' => RoleResource::collection(Role::orderBy('id')->get())]);
    }

    public function units(): JsonResponse
    {
        return response()->json(['data' => UnitResource::collection(Unit::orderBy('code')->get())]);
    }

    public function storeUnit(Request $request): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403, 'Kamu tidak memiliki akses ke halaman ini.');

        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'unique:units,code'],
            'name' => ['required', 'string', 'max:80'],
        ]);

        return response()->json([
            'message' => 'Satuan pekerjaan berhasil ditambahkan.',
            'data' => new UnitResource(Unit::create($data)),
        ], 201);
    }
}
