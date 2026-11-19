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

test('a permission can be granted directly to one user and removed without changing their role', function () {
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $otherPermission = Permission::create(['name' => 'reports.export', 'guard_name' => 'web']);
    $role = Role::create(['name' => 'staff', 'guard_name' => 'web']);
    $role->givePermissionTo($permission);
    $target = User::create(['name' => 'Ana Cruz', 'username' => 'ac-person', 'password' => 'password']);
    $target->assignRole($role)->givePermissionTo($otherPermission);
    $list = route('permissions.users.index', ['permission' => $permission, 'search' => 'ac-person']);
    $update = route('permissions.users.update', [$permission, $target]);

    $this->actingAs($this->manager)->getJson($list)->assertOk()
        ->assertJsonPath('users.total', 1)->assertJsonPath('users.data.0.id', $target->id)
        ->assertJsonPath('users.data.0.has_direct_permission', false)->assertJsonMissingPath('users.data.0.password');

    $this->putJson($update, ['granted' => true])->assertOk()->assertJsonPath('users_count', 1);
    $this->putJson($update, ['granted' => true])->assertOk()->assertJsonPath('users_count', 1);
    $this->getJson($list)->assertOk()->assertJsonPath('users.data.0.has_direct_permission', true);
    expect($target->fresh()->hasDirectPermission($permission))->toBeTrue();
    $this->putJson($update, ['granted' => false])->assertOk()->assertJsonPath('users_count', 0);

    $target->refresh();
    expect($target->hasDirectPermission($permission))->toBeFalse()
        ->and($target->hasPermissionTo($permission))->toBeTrue()
        ->and($target->hasDirectPermission($otherPermission))->toBeTrue();
});

test('only administrators who hold a permission can delegate it', function () {
    $pageAccess = Permission::create(['name' => 'view permissions', 'guard_name' => 'web']);
    $permission = Permission::create(['name' => 'reports.view', 'guard_name' => 'web']);
    $otherGuard = Permission::create(['name' => 'reports.view', 'guard_name' => 'api']);
    $delegate = User::create(['name' => 'Delegate', 'username' => 'delegate', 'password' => 'password']);
    $target = User::create(['name' => 'Staff', 'username' => 'staff', 'password' => 'password']);
    $delegate->givePermissionTo($pageAccess);
    $update = route('permissions.users.update', [$permission, $target]);

    $this->putJson($update, ['granted' => true])->assertUnauthorized();
    $this->actingAs($delegate)->getJson(route('permissions.users.index', $permission))->assertForbidden();
    $this->putJson($update, ['granted' => true])->assertForbidden();
    $this->getJson(route('permissions.index', ['search' => 'reports.view']))->assertOk()
        ->assertJsonPath('permissions.data.0.can_assign_users', false);

    $delegate->givePermissionTo($permission);
    $this->getJson(route('permissions.index', ['search' => 'reports.view']))->assertOk()
        ->assertJsonPath('permissions.data.0.can_assign_users', true);
    $this->putJson($update, ['granted' => null])->assertUnprocessable()->assertJsonValidationErrors('granted');
    $this->putJson($update, ['granted' => true])->assertOk();
    $this->actingAs($target)->putJson($update, ['granted' => true])->assertForbidden();
    $this->actingAs($this->manager)->getJson(route('permissions.users.index', $otherGuard))->assertNotFound();
    $this->putJson(route('permissions.users.update', [$otherGuard, $target]), ['granted' => true])->assertNotFound();
});
