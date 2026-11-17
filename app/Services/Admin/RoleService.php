<?php

namespace App\Services\Admin;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class RoleService
{
    public function paginate(string $search = ''): LengthAwarePaginator
    {
        return Role::query()->where('guard_name', 'web')
            ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
            ->with('permissions:id,name')
            ->withCount(['permissions', 'users'])
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString();
    }

    public function create(array $data): Role
    {
        return DB::transaction(function () use ($data) {
            $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
            $role->syncPermissions(array_map('intval', $data['permissions'] ?? []));

            return $role;
        });
    }

    public function update(Role $role, array $data): Role
    {
        abort_unless($role->guard_name === 'web', 404);

        if ($role->name === 'superadmin') {
            throw ValidationException::withMessages(['role' => 'The superadmin role cannot be renamed.']);
        }

        return DB::transaction(function () use ($role, $data) {
            $role->update(['name' => $data['name']]);
            if (array_key_exists('permissions', $data)) {
                $role->syncPermissions(array_map('intval', $data['permissions']));
            }

            return $role;
        });
    }

    public function delete(Role $role): void
    {
        abort_unless($role->guard_name === 'web', 404);

        if ($role->name === 'superadmin' || $role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'The superadmin role and roles assigned to users cannot be deleted.']);
        }

        $role->delete();
    }
}
