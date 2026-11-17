<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->manager = User::create([
        'name' => 'Permission Manager', 'username' => 'manager', 'password' => 'password', 'status' => 'active',
    ]);
    $this->manager->assignRole(Role::create(['name' => 'superadmin', 'guard_name' => 'web']));
});

test('permissions require authentication', function () {
    $this->get(route('permissions.index'))->assertRedirect(route('login'));
    $this->get(route('permissions.create'))->assertRedirect(route('login'));
    $this->post(route('permissions.store'), ['name' => 'users.view'])->assertRedirect(route('login'));
});

test('the old create url redirects to the list with the create modal requested', function () {
    $this->actingAs($this->manager)->get(route('permissions.create'))
        ->assertRedirect(route('permissions.index', ['create' => 1]));
});

test('permissions are searchable and paginated with assignment counts', function () {
    foreach (range(1, 12) as $index) {
        Permission::create(['name' => sprintf('reports.%02d', $index), 'guard_name' => 'web']);
    }
    Permission::create(['name' => 'users.view', 'guard_name' => 'web']);
    $permission = Permission::findByName('reports.01');
    $this->manager->givePermissionTo($permission);
    $this->manager->roles->first()->givePermissionTo($permission);

    $this->actingAs($this->manager)->get(route('permissions.index', ['search' => 'reports.']))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Permissions/Index')
            ->where('canManage', true)
            ->where('permissions.total', 12)
            ->has('permissions.data', 10)
            ->where('permissions.data.0.roles_count', 1)
            ->where('permissions.data.0.users_count', 1)
            ->where('filters.search', 'reports.')
        );
    $this->get(route('permissions.index', ['search' => 'reports.', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('permissions.data', 2));
});

test('superadmins can create rename and delete an unassigned permission', function () {
    $returnUrl = route('permissions.index', ['search' => 'reports', 'page' => 2]);
    $this->actingAs($this->manager)->from($returnUrl)->post(route('permissions.store'), ['name' => '  reports.view  '])
        ->assertRedirect($returnUrl)
        ->assertSessionHas('permission_success', 'Permission created.')
        ->assertSessionHasNoErrors();
    $permission = Permission::findByName('reports.view');
    $this->post(route('permissions.update', $permission), ['name' => 'reports.export'])
        ->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'reports.export', 'guard_name' => 'web']);
    $this->delete(route('permissions.destroy', $permission))->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

test('permission names are required and unique within the web guard', function () {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $this->actingAs($this->manager)->post(route('permissions.store'), ['name' => '   '])->assertSessionHasErrors('name');
    $this->post(route('permissions.store'), ['name' => 'reports.view'])->assertSessionHasErrors('name');
    $this->post(route('permissions.update', $permission), ['name' => 'reports.view'])->assertSessionHasNoErrors();
    $this->post(route('permissions.store'), ['name' => str_repeat('a', 256)])->assertSessionHasErrors('name');
});

test('users without page access cannot view or change permissions', function () {
    $user = User::create(['name' => 'Viewer', 'username' => 'viewer', 'password' => 'password']);
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $this->actingAs($user)->get(route('permissions.index'))
        ->assertForbidden();
    $this->post(route('permissions.store'), ['name' => 'reports.export'])->assertForbidden();
    $this->post(route('permissions.update', $permission), ['name' => 'reports.export'])->assertForbidden();
    $this->delete(route('permissions.destroy', $permission))->assertForbidden();
});

test('axios requests receive json for listing and permission changes', function () {
    $this->actingAs($this->manager)->postJson(route('permissions.store'), ['name' => 'reports.view'])
        ->assertCreated()->assertJsonPath('message', 'Permission created.');
    $permission = Permission::findByName('reports.view');
    $this->getJson(route('permissions.index', ['search' => 'reports']))
        ->assertOk()->assertJsonPath('permissions.total', 1)
        ->assertJsonPath('permissions.data.0.name', 'reports.view');
    $this->postJson(route('permissions.update', $permission), ['name' => 'reports.export'])
        ->assertOk()->assertJsonPath('message', 'Permission updated.');
    $this->assertDatabaseHas('permissions', ['id' => $permission->id, 'name' => 'reports.export']);
    $this->deleteJson(route('permissions.destroy', $permission))
        ->assertOk()->assertJsonPath('message', 'Permission deleted.');
    $this->assertDatabaseMissing('permissions', ['id' => $permission->id]);
});

test('axios requests receive json validation and authorization errors', function () {
    $this->postJson(route('permissions.store'), ['name' => 'reports.view'])->assertUnauthorized();
    $this->actingAs($this->manager)->postJson(route('permissions.store'), ['name' => ''])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $this->manager->givePermissionTo($permission);
    $this->deleteJson(route('permissions.destroy', $permission))
        ->assertUnprocessable()->assertJsonValidationErrors('permission');
    $viewer = User::create(['name' => 'Viewer', 'username' => 'viewer', 'password' => 'password']);
    $this->actingAs($viewer)->postJson(route('permissions.store'), ['name' => 'reports.export'])->assertForbidden();
});

test('assigned permissions cannot be deleted', function (string $assignment) {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    if ($assignment === 'role') {
        $this->manager->roles->first()->givePermissionTo($permission);
    } else {
        $this->manager->givePermissionTo($permission);
    }
    $this->actingAs($this->manager)->delete(route('permissions.destroy', $permission))
        ->assertSessionHasErrors('permission');
    $this->assertDatabaseHas('permissions', ['id' => $permission->id]);
})->with(['role', 'user']);
