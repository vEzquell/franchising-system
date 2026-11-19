<?php

use App\Models\Franchise;
use App\Models\Requirement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::create(['name' => 'Staff', 'username' => 'requirements-staff', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
    $this->processPermission = Permission::create(['name' => 'process transactions', 'guard_name' => 'web']);
});

test('transaction processors can add and edit requirements for each transaction type', function () {
    $this->staff->givePermissionTo($this->processPermission);
    $this->actingAs($this->staff);

    foreach (['application', 'renewal', 'amendment', 'substitution'] as $transactionType) {
        $this->postJson(route('requirements.store'), ['transaction_type' => $transactionType, 'name' => 'Valid ID'])
            ->assertCreated()->assertJsonPath('requirement.transaction_type', $transactionType);
    }

    $requirement = Requirement::where('transaction_type', 'renewal')->sole();
    $this->postJson(route('requirements.update', $requirement), [
        'transaction_type' => 'renewal', 'name' => 'Valid ID', 'description' => 'Bring the original.',
    ])->assertOk()->assertJsonPath('requirement.description', 'Bring the original.');
    $this->get(route('requirements.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/Requirements/Index')->where('transactionTypes.renewal', 'Renewal')->has('requirements', 4));

    $this->assertDatabaseHas('requirements', ['id' => $requirement->id, 'transaction_type' => 'renewal', 'description' => 'Bring the original.']);
});

test('invalid categories and duplicate names within a transaction type are rejected', function () {
    $this->staff->givePermissionTo($this->processPermission);
    $this->actingAs($this->staff);
    $data = ['transaction_type' => 'application', 'name' => 'Application form'];

    $this->postJson(route('requirements.store'), $data)->assertCreated();
    $this->postJson(route('requirements.store'), [...$data, 'name' => ' Application form '])
        ->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(route('requirements.store'), ['transaction_type' => 'other', 'name' => '', 'description' => str_repeat('x', 1001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['transaction_type', 'name', 'description']);
    $this->assertDatabaseCount('requirements', 1);
});

test('transaction viewers can read filing guidance but cannot manage requirements', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-REQ']);
    $requirement = Requirement::create(['transaction_type' => 'application', 'name' => 'Application form']);
    $this->get(route('requirements.index'))->assertRedirect(route('login'));

    $this->actingAs($this->staff)->get(route('requirements.index'))->assertForbidden();
    $this->postJson(route('requirements.store'), [])->assertForbidden();
    $this->postJson(route('requirements.update', $requirement), [])->assertForbidden();
    $this->getJson(route('transactions.options', $franchise))->assertOk()
        ->assertJsonPath('requirements.0.transaction_type', 'application')
        ->assertJsonPath('requirements.0.name', 'Application form');
});
