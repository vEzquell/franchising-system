<?php

use App\Models\Liner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::create(['name' => 'Liner Staff', 'username' => 'liner-staff', 'password' => 'password']);
    $this->permission = Permission::create(['name' => 'view liners', 'guard_name' => 'web']);
});

test('the liner page and its write routes require access', function () {
    $liner = Liner::create(['name' => 'Central Liner', 'route' => 'Market to Terminal']);

    $this->get(route('liners.index'))->assertRedirect(route('login'));
    $this->postJson(route('liners.store'), [])->assertUnauthorized();

    $this->actingAs($this->staff)->get(route('liners.index'))->assertForbidden();
    $this->postJson(route('liners.store'), [])->assertForbidden();
    $this->postJson(route('liners.update', $liner), [])->assertForbidden();
});

test('the liner page lists records and searches by name or route', function () {
    $this->staff->givePermissionTo($this->permission);
    Liner::create(['name' => 'Central Liner', 'route' => 'Market to Terminal']);

    foreach (range(1, 10) as $number) {
        Liner::create(['name' => "East Liner {$number}", 'route' => "East Route {$number}"]);
    }

    $this->actingAs($this->staff)->get(route('liners.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Liner/Index')
            ->has('liners.data', 10)
            ->where('liners.total', 11)
            ->where('liners.data.0.name', 'Central Liner'));

    $this->getJson(route('liners.index', ['page' => 2]))
        ->assertOk()->assertJsonCount(1, 'liners.data');

    foreach (['central', 'terminal'] as $search) {
        $this->getJson(route('liners.index', ['search' => $search]))
            ->assertOk()
            ->assertJsonPath('liners.total', 1)
            ->assertJsonPath('liners.data.0.route', 'Market to Terminal');
    }

    $this->getJson(route('liners.index', ['search' => 'missing']))
        ->assertOk()->assertJsonPath('liners.total', 0);
    $this->getJson(route('liners.index', ['page' => 0, 'search' => str_repeat('x', 192)]))
        ->assertUnprocessable()->assertJsonValidationErrors(['page', 'search']);
});

test('staff can add and edit a liner while keeping its own name', function () {
    $this->staff->givePermissionTo($this->permission);

    $this->actingAs($this->staff)->postJson(route('liners.store'), [
        'name' => ' Central Liner ',
        'route' => 'Market to Terminal',
    ])->assertCreated()->assertJsonPath('message', 'Liner added.');

    $liner = Liner::sole();
    $this->assertDatabaseHas('liners', ['id' => $liner->id, 'name' => 'Central Liner', 'route' => 'Market to Terminal']);

    $this->postJson(route('liners.update', $liner), [
        'name' => 'Central Liner',
        'route' => 'Market to City Hall',
    ])->assertOk()->assertJsonPath('message', 'Liner updated.');

    $this->assertDatabaseHas('liners', ['id' => $liner->id, 'name' => 'Central Liner', 'route' => 'Market to City Hall']);
    $this->assertDatabaseCount('liners', 1);
});

test('liner writes reject missing, oversized and duplicate values', function () {
    $this->staff->givePermissionTo($this->permission);
    $existing = ['name' => 'Central Liner', 'route' => 'Market to Terminal'];
    Liner::create($existing);
    $other = Liner::create(['name' => 'East Liner', 'route' => 'City Hall to Market']);

    $this->actingAs($this->staff)->postJson(route('liners.store'), [])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'route']);
    $this->postJson(route('liners.store'), ['name' => str_repeat('x', 192), 'route' => str_repeat('x', 192)])
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'route']);
    $this->postJson(route('liners.store'), $existing)
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'route']);
    $this->postJson(route('liners.update', $other), $existing)
        ->assertUnprocessable()->assertJsonValidationErrors(['name', 'route']);

    $this->assertDatabaseHas('liners', ['id' => $other->id, 'name' => 'East Liner', 'route' => 'City Hall to Market']);
    $this->assertDatabaseCount('liners', 2);
});
