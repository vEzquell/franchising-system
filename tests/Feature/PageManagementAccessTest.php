<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

test('page access permits the corresponding management functions', function (string $section) {
    $user = User::create(['name' => 'Manager', 'username' => 'manager', 'password' => 'password']);
    $user->givePermissionTo(Permission::create(['name' => "view {$section}", 'guard_name' => 'web']));
    $this->actingAs($user)->getJson(route($section === 'users' ? 'users' : "{$section}.index"))
        ->assertOk()->assertJsonPath('canManage', true);
    $this->get(route("{$section}.create"))->assertRedirect();
    if ($section === 'users') {
        $role = Role::create(['name' => 'staff', 'guard_name' => 'web']);
        $data = ['name' => 'Staff', 'username' => 'staff', 'password' => 'password', 'password_confirmation' => 'password', 'status' => 'active', 'role_id' => $role->id];
        $this->postJson(route('users.store'), $data)->assertCreated();
        $target = User::where('username', 'staff')->firstOrFail();
        $this->postJson(route('users.update', $target), [...$data, 'name' => 'Updated'])->assertOk();
    } else {
        $this->postJson(route("{$section}.store"), ['name' => 'example'])->assertCreated();
        $target = ($section === 'roles' ? Role::class : Permission::class)::findByName('example');
        $this->postJson(route("{$section}.update", $target), ['name' => 'updated'])->assertOk();
        $this->deleteJson(route("{$section}.destroy", $target))->assertOk();
    }
})->with(['users', 'roles', 'permissions']);

test('users without page access cannot invoke its functions', function (string $section) {
    $user = User::create(['name' => 'Viewer', 'username' => 'viewer', 'password' => 'password']);
    $this->actingAs($user)->getJson(route($section === 'users' ? 'users' : "{$section}.index"))->assertForbidden();
    $this->get(route("{$section}.create"))->assertForbidden();
    $this->postJson(route("{$section}.store"), [])->assertForbidden();
    $target = match ($section) {
        'users' => $user,
        'roles' => Role::create(['name' => 'staff', 'guard_name' => 'web']),
        'permissions' => Permission::create(['name' => 'example', 'guard_name' => 'web']),
    };
    $this->postJson(route("{$section}.update", $target), [])->assertForbidden();
    if ($section !== 'users') {
        $this->deleteJson(route("{$section}.destroy", $target))->assertForbidden();
    }
})->with(['users', 'roles', 'permissions']);
