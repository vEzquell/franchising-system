<?php

use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::create(['name' => 'Owner Staff', 'username' => 'owner-staff', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view owners', 'guard_name' => 'web']));
});

test('owner page and mutations require page access', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $this->get(route('owners.index'))->assertRedirect(route('login'));
    $this->postJson(route('owners.store'), [])->assertUnauthorized();
    $other = User::create(['name' => 'Other', 'username' => 'owner-other', 'password' => 'password']);
    $this->actingAs($other)->getJson(route('owners.index'))->assertForbidden();
    $this->postJson(route('owners.store'), [])->assertForbidden();
    $this->postJson(route('owners.update', $owner), [])->assertForbidden();
    $other->assignRole(Role::create(['name' => 'superadmin', 'guard_name' => 'web']));
    $this->getJson(route('owners.index'))->assertOk();
});

test('owners are paginated searchable and omit franchise assignments', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'middle_name' => 'Maria', 'last_name' => 'Cruz', 'contact_number' => '09123456789', 'address' => 'Main Street']);
    foreach (range(1, 12) as $index) {
        FranchiseOwner::create(['first_name' => 'Other', 'last_name' => sprintf('Owner %02d', $index)]);
    }
    $franchise = Franchise::create(['franchise_number' => 'F-001']);
    $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => today()->subYear()]);
    $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => today()->subYears(2), 'ended_at' => today()->subYear()]);
    $owner->ownerships()->create(['franchise_id' => $franchise->id, 'started_at' => today()->addYear()]);
    $this->actingAs($this->staff)->get(route('owners.index'))->assertInertia(fn (Assert $page) => $page->component('Admin/Owner/Index')->has('owners.data', 10)->where('owners.total', 13));
    $this->getJson(route('owners.index', ['page' => 2]))->assertOk()->assertJsonCount(3, 'owners.data');
    foreach (['Ana Cruz', '091234', 'Main Street'] as $search) {
        $this->getJson(route('owners.index', ['search' => $search]))->assertOk()->assertJsonPath('owners.total', 1)->assertJsonPath('owners.data.0.contact_number', '09123456789')->assertJsonMissingPath('owners.data.0.ownerships');
    }
    $this->getJson(route('owners.index', ['search' => 'missing']))->assertOk()->assertJsonPath('owners.total', 0);
});

test('owners can be created and edited without losing phone formatting', function () {
    $data = ['first_name' => ' Ana ', 'middle_name' => '', 'last_name' => ' Cruz ', 'contact_number' => '+639123456789', 'address' => 'Main Street'];
    $response = $this->actingAs($this->staff)->postJson(route('owners.store'), $data)->assertCreated();
    $owner = FranchiseOwner::sole();
    $response->assertJsonPath('owner.id', $owner->id)->assertJsonPath('owner.first_name', 'Ana');
    expect($owner->first_name)->toBe('Ana')->and($owner->last_name)->toBe('Cruz')->and($owner->middle_name)->toBeNull()->and($owner->contact_number)->toBe('+639123456789');
    $this->postJson(route('owners.update', $owner), [...$data, 'last_name' => 'Reyes', 'address' => ''])->assertOk();
    expect($owner->fresh()->last_name)->toBe('Reyes')->and($owner->fresh()->address)->toBeNull();
    $this->postJson(route('owners.update', 999999), $data)->assertNotFound();
});

test('owner validation respects database limits', function () {
    $this->actingAs($this->staff)->postJson(route('owners.store'), ['first_name' => ' ', 'last_name' => '', 'middle_name' => str_repeat('x', 51), 'address' => str_repeat('x', 256), 'contact_number' => str_repeat('1', 21)])
        ->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name', 'middle_name', 'address', 'contact_number']);
    $this->getJson(route('owners.index', ['search' => str_repeat('x', 256), 'page' => 0]))->assertUnprocessable()->assertJsonValidationErrors(['search', 'page']);
    $this->assertDatabaseCount('franchise_owners', 0);
});

test('owner suffix is optional searchable editable and validated', function () {
    $data = ['first_name' => 'Juan', 'last_name' => 'Cruz', 'suffix' => ' Jr. '];
    $this->actingAs($this->staff)->postJson(route('owners.store'), $data)->assertCreated();
    $owner = FranchiseOwner::sole();
    expect($owner->suffix)->toBe('Jr.');
    $this->getJson(route('owners.index', ['search' => 'Juan Jr.']))->assertOk()->assertJsonPath('owners.total', 1)->assertJsonPath('owners.data.0.suffix', 'Jr.');
    $this->postJson(route('owners.update', $owner), [...$data, 'suffix' => 'Sr.'])->assertOk();
    expect($owner->fresh()->suffix)->toBe('Sr.');
    $this->postJson(route('owners.update', $owner), [...$data, 'suffix' => ''])->assertOk();
    expect($owner->fresh()->suffix)->toBeNull();
    $this->postJson(route('owners.store'), [...$data, 'suffix' => str_repeat('x', 21)])->assertUnprocessable()->assertJsonValidationErrors('suffix');
});

test('owners can be assigned a franchise and become available for renewal', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $franchise = Franchise::create(['franchise_number' => 'F-ASSIGN']);
    $this->actingAs($this->staff)->getJson(route('owners.franchises', ['search' => 'ASSIGN']))->assertOk()->assertJsonPath('franchises.0.id', $franchise->id);
    $this->assertDatabaseCount('franchise_ownerships', 0);
    $this->postJson(route('owners.franchises.store', $owner), ['franchise_id' => $franchise->id, 'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString()])->assertCreated();
    $this->assertDatabaseHas('franchise_ownerships', ['franchise_owner_id' => $owner->id, 'franchise_id' => $franchise->id, 'ended_at' => null]);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
    $this->getJson(route('transactions.options', $franchise))->assertOk()->assertJsonPath('current_owner_ids.0', $owner->id);
});

test('assignment preserves existing ownership and rejects invalid dates', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $other = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $franchise = Franchise::create(['franchise_number' => 'F-ASSIGN']);
    $data = ['franchise_id' => $franchise->id, 'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString()];
    $this->actingAs($this->staff)->postJson(route('owners.franchises.store', $owner), $data)->assertCreated();
    $this->postJson(route('owners.franchises.store', $other), $data)->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
    $this->postJson(route('owners.franchises.store', $owner), $data)->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'started_at' => today()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('started_at');
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'franchise_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
    $this->assertDatabaseCount('franchise_ownerships', 1);
});

test('only owner page users can assign franchises', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $this->postJson(route('owners.franchises.store', $owner), [])->assertUnauthorized();
    $user = User::create(['name' => 'Other', 'username' => 'noassign', 'password' => 'password']);
    $this->actingAs($user)->postJson(route('owners.franchises.store', $owner), [])->assertForbidden();
    $this->getJson(route('owners.franchises'))->assertForbidden();
});

test('assignment saves franchise expiration and rejects invalid dates without partial writes', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $franchise = Franchise::create(['franchise_number' => 'F-EXPIRY', 'date_issued' => today()]);
    $data = ['franchise_id' => $franchise->id, 'started_at' => today()->subMonth()->toDateString()];
    $this->actingAs($this->staff)->postJson(route('owners.franchises.store', $owner), $data)->assertUnprocessable()->assertJsonValidationErrors('expiration_date');
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'expiration_date' => today()->subMonths(2)->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('expiration_date');
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'expiration_date' => today()->subDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('expiration_date');
    $this->assertDatabaseCount('franchise_ownerships', 0);
    expect($franchise->fresh()->expiration_date)->toBeNull();
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'expiration_date' => today()->addYear()->toDateString()])->assertCreated();
    expect($franchise->fresh()->expiration_date->toDateString())->toBe(today()->addYear()->toDateString());
    $this->assertDatabaseCount('franchise_ownerships', 1);
});
