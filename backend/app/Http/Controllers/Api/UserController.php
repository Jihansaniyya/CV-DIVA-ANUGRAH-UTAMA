<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', User::class);

        $users = User::with('role')
            ->when($request->filled('q'), function ($query) use ($request) {
                $q = '%'.$request->string('q').'%';
                $query->where(fn ($sub) => $sub->where('name', 'like', $q)->orWhere('username', 'like', $q));
            })
            ->when($request->filled('role'), fn ($query) => $query->whereHas('role', fn ($r) => $r->where('code', $request->string('role'))))
            ->when($request->filled('status'), fn ($query) => $query->where('is_active', $request->string('status') === 'aktif'))
            ->orderBy('name')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = User::create($request->validated());

        return response()->json([
            'message' => 'Pengguna berhasil ditambahkan.',
            'data' => new UserResource($user->load('role')),
        ], 201);
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return response()->json(['data' => new UserResource($user->load('role'))]);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'message' => 'Data pengguna berhasil diperbarui.',
            'data' => new UserResource($user->load('role')),
        ]);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('delete', $user);

        if ($user->managedProjects()->exists() || $user->progressReports()->exists()) {
            return response()->json([
                'message' => 'Pengguna tidak dapat dihapus karena masih terhubung dengan proyek atau laporan. Nonaktifkan akun sebagai gantinya.',
            ], 422);
        }

        $user->delete();

        return response()->json(['message' => 'Pengguna berhasil dihapus.']);
    }

    /** Aktifkan atau nonaktifkan akun pengguna. */
    public function toggleActive(User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        return response()->json([
            'message' => $user->is_active ? 'Akun berhasil diaktifkan.' : 'Akun berhasil dinonaktifkan.',
            'data' => new UserResource($user->load('role')),
        ]);
    }
}
