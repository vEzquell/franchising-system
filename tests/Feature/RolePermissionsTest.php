<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'password']);
    $this->manager->assignRole(Role::create(['name' => 'superadmin', 'guard_name' => 'web']));
});

test('superadmins can create replace and clear role permissions', function () {
    $view = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $export = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $this->actingAs($this->manager)->postJson(route('roles.store'), [
        'name' => 'reporter', 'permissions' => [$view->id],
    ])->assertCreated();
    $role = Role::findByName('reporter');
    expect($role->permissions->modelKeys())->toBe([$view->id]);
    $this->postJson(route('roles.update', $role), [
        'name' => 'exporter', 'permissions' => [$export->id],
    ])->assertOk();
    expect($role->fresh()->permissions->modelKeys())->toBe([$export->id]);
    $this->postJson(route('roles.update', $role), ['name' => 'exporter', 'permissions' => []])->assertOk();
    expect($role->fresh()->permissions)->toHaveCount(0);
});

test('role listing includes available web permissions and current selections', function () {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    Permission::create(['name' => 'api.view', 'guard_name' => 'api']);
    $role = Role::create(['name' => 'reporter', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $this->actingAs($this->manager)->getJson(route('roles.index', ['search' => 'reporter']))
        ->assertOk()->assertJsonCount(1, 'permissions')
        ->assertJsonPath('permissions.0.id', $permission->id)
        ->assertJsonPath('roles.data.0.permissions.0.id', $permission->id);
});

test('invalid or foreign guard permissions cannot change a role', function () {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $foreign = Permission::create(['name' => 'api.view', 'guard_name' => 'api']);
    $role = Role::create(['name' => 'reporter', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    foreach ([[$foreign->id], [999999], ['invalid'], [$permission->id, $permission->id]] as $permissions) {
        $this->actingAs($this->manager)->postJson(route('roles.update', $role), [
            'name' => 'changed', 'permissions' => $permissions,
        ])->assertUnprocessable()->assertJsonValidationErrors('permissions.0');
    }
    expect($role->fresh()->name)->toBe('reporter');
    expect($role->fresh()->permissions->modelKeys())->toBe([$permission->id]);
});

test('ordinary users cannot assign role permissions', function () {
    $user = User::create(['name' => 'Viewer', 'username' => 'viewer', 'password' => 'password']);
    $role = Role::create(['name' => 'reporter', 'guard_name' => 'web']);
    $this->actingAs($user)->postJson(route('roles.store'), ['name' => 'new', 'permissions' => []])->assertForbidden();
    $this->postJson(route('roles.update', $role), ['name' => 'changed', 'permissions' => []])->assertForbidden();
});
