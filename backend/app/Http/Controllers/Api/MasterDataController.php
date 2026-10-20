<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUnitRequest;
use App\Http\Requests\UpdateUnitRequest;
use App\Http\Resources\RoleResource;
use App\Http\Resources\UnitResource;
use App\Models\Role;
use App\Models\Unit;
use App\Services\UnitService;
use Illuminate\Http\JsonResponse;

/** Master data yang dipakai lintas modul: peran pengguna dan satuan pekerjaan. */
class MasterDataController extends Controller
{
    public function __construct(private readonly UnitService $units) {}

    public function roles(): JsonResponse
    {
        return response()->json(['data' => RoleResource::collection(Role::orderBy('id')->get())]);
    }

    public function units(): JsonResponse
    {
        return response()->json(['data' => UnitResource::collection(Unit::withCount('workItems')->orderBy('code')->get())]);
    }

    public function storeUnit(StoreUnitRequest $request): JsonResponse
    {
        return response()->json([
            'message' => 'Satuan pekerjaan berhasil ditambahkan.',
            'data' => new UnitResource($this->units->create($request->validated())),
        ], 201);
    }

    public function updateUnit(UpdateUnitRequest $request, Unit $unit): JsonResponse
    {
        return response()->json([
            'message' => 'Satuan pekerjaan berhasil diperbarui.',
            'data' => new UnitResource($this->units->update($unit, $request->validated())),
        ]);
    }

    public function destroyUnit(Unit $unit): JsonResponse
    {
        $this->units->delete($unit);

        return response()->json(['message' => 'Satuan pekerjaan berhasil dihapus.', 'data' => null]);
    }
}
