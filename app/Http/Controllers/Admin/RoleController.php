<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveRoleRequest;
use App\Services\Admin\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function __construct(private readonly RoleService $roleService) {}

    public function index(Request $request): Response|JsonResponse
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:255']]);
        $search = trim($filters['search'] ?? '');
        $data = [
            'permissions' => $request->user()->can('view roles')
                ? Permission::where('guard_name', 'web')->orderBy('name')->get(['id', 'name'])
                : [],
            'roles' => $this->roleService->paginate($search),
            'filters' => ['search' => $search],
            'canManage' => $request->user()->can('view roles'),
        ];

        return $request->wantsJson() ? response()->json($data) : Inertia::render('Admin/Roles/Index', $data);
    }

    public function store(SaveRoleRequest $request): JsonResponse
    {
        $this->roleService->create($request->validated());

        return response()->json(['message' => 'Role created.'], 201);
    }

    public function update(SaveRoleRequest $request, Role $role): JsonResponse
    {
        $this->roleService->update($role, $request->validated());

        return response()->json(['message' => 'Role updated.']);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_unless($request->user()->can('view roles'), 403);
        $this->roleService->delete($role);

        return response()->json(['message' => 'Role deleted.']);
    }
}
