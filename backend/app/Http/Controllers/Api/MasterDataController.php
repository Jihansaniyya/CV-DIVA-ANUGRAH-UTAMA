<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UnitResource;
use App\Models\Role;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;

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

    public function storeUnit(StoreUnitRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Satuan pekerjaan berhasil ditambahkan.',
            'data' => new UnitResource(Unit::create($request->validated())),
        ], 201);
    }
}
