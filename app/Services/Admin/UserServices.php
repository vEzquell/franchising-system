<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UserServices
{
    public function paginate(string $search = ''): LengthAwarePaginator
    {
        return User::query()->select(['id', 'name', 'username', 'status', 'last_login_at', 'created_at'])
            ->with('roles:id,name')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('username', 'like', '%'.$search.'%')
                    ->orWhere('status', 'like', '%'.$search.'%')
                    ->orWhereHas('roles', fn ($roles) => $roles->where('name', 'like', '%'.$search.'%'));
            }))
            ->orderByDesc('id')->paginate(10)->withQueryString();
    }

    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $role = Role::query()->where('guard_name', 'web')->findOrFail($data['role_id']);
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'password' => $data['password'],
                'status' => $data['status'],
            ]);
            $user->assignRole($role);

            return $user;
        });
    }

    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data) {
            $role = Role::query()->where('guard_name', 'web')->findOrFail($data['role_id']);
            $attributes = [
                'name' => $data['name'],
                'username' => $data['username'],
                'status' => $data['status'],
            ];

            if (! empty($data['password'])) {
                $attributes['password'] = $data['password'];
            }

            $user->update($attributes);
            $user->syncRoles([$role]);

            return $user;
        });
    }
}
