<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Services\Admin\UserServices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly UserServices $userServices) {}

    public function users(Request $request): Response|JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($filters['search'] ?? '');
        $canManage = $request->user()->can('view users');
        $data = [
            'users' => $this->userServices->paginate($search),
            'roles' => $canManage ? Role::query()->where('guard_name', 'web')->orderBy('name')->get(['id', 'name']) : [],
            'canManage' => $canManage,
            'filters' => ['search' => $search],
        ];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/User/Index', $data);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $this->userServices->create($request->validated());

        return response()->json(['message' => 'User created.'], 201);
    }

    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->userServices->update($user, $request->validated());

        return response()->json(['message' => 'User updated.']);
    }
}
