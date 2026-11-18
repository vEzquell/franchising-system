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
    $this->viewer = User::create(['name' => 'Viewer', 'username' => 'viewer', 'password' => 'password']);
    $this->viewer->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
});

test('franchise listing requires authentication and page permission', function () {
    $this->get(route('franchise.index'))->assertRedirect(route('login'));
    $other = User::create(['name' => 'Other', 'username' => 'other', 'password' => 'password']);
    $this->actingAs($other)->getJson(route('franchise.index'))->assertForbidden();
    $other->assignRole(Role::create(['name' => 'superadmin', 'guard_name' => 'web']));
    $this->getJson(route('franchise.index'))->assertOk();
});

test('franchise listing renders the index and paginates stable results', function () {
    foreach (range(1, 12) as $index) {
        Franchise::create(['franchise_number' => sprintf('F-%03d', $index)]);
    }
    $this->actingAs($this->viewer)->get(route('franchise.index'))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/Franchise/Index')
            ->has('franchises.data', 10)->where('franchises.total', 12)
            ->where('franchises.data.0.franchise_number', 'F-001')->where('filters.status', ''));
    $this->getJson(route('franchise.index', ['page' => 2]))->assertOk()
        ->assertJsonCount(2, 'franchises.data')->assertJsonPath('franchises.data.0.franchise_number', 'F-011');
});

test('search and status filters include only current owners without leaking unrelated statuses', function () {
    $this->travelTo(now()->setDate(2026, 11, 17));
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $franchise = Franchise::create(['franchise_number' => 'F-001', 'status' => 'active']);
    $franchise->ownerships()->create(['franchise_owner_id' => $owner->id, 'started_at' => '2026-01-01']);
    $franchise->transactions()->create(['transaction_type' => 'application', 'date_filed' => '2026-01-01']);
    foreach ([['F-002', 'expired', '2026-01-01', null], ['F-003', 'active', '2025-01-01', '2026-11-17'], ['F-004', 'active', '2027-01-01', null]] as [$number, $status, $start, $end]) {
        $other = Franchise::create(['franchise_number' => $number, 'status' => $status]);
        $other->ownerships()->create(['franchise_owner_id' => $owner->id, 'started_at' => $start, 'ended_at' => $end]);
    }
    $this->actingAs($this->viewer)->getJson(route('franchise.index', ['search' => '  Cruz  ', 'status' => 'active']))
        ->assertOk()->assertJsonPath('filters.search', 'Cruz')->assertJsonPath('franchises.total', 1)
        ->assertJsonPath('franchises.data.0.franchise_number', 'F-001')
        ->assertJsonPath('franchises.data.0.transactions_count', 1)
        ->assertJsonPath('franchises.data.0.ownerships.0.owner.first_name', 'Ana');
    $this->getJson(route('franchise.index', ['search' => 'F-004']))->assertOk()->assertJsonPath('franchises.total', 1)
        ->assertJsonCount(0, 'franchises.data.0.ownerships');
    $this->getJson(route('franchise.index', ['search' => 'missing']))->assertOk()->assertJsonPath('franchises.total', 0);
});

test('franchise filters reject invalid input', function () {
    $this->actingAs($this->viewer)->getJson(route('franchise.index', ['status' => 'invalid', 'search' => str_repeat('x', 256), 'page' => 0]))
        ->assertUnprocessable()->assertJsonValidationErrors(['status', 'search', 'page']);
});

test('page viewers can edit franchise details with validated dates and unique numbers', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-EDIT']);
    Franchise::create(['franchise_number' => 'F-TAKEN']);
    $payload = ['franchise_number' => 'F-UPDATED', 'status' => 'suspended', 'date_issued' => '2026-01-01', 'expiration_date' => '2027-01-01'];
    $this->actingAs($this->viewer)->postJson(route('franchise.update', $franchise), $payload)->assertOk();
    $this->assertDatabaseHas('franchises', ['id' => $franchise->id, 'franchise_number' => 'F-UPDATED', 'status' => 'suspended']);
    $this->postJson(route('franchise.update', $franchise), [...$payload, 'franchise_number' => 'F-TAKEN'])->assertUnprocessable()->assertJsonValidationErrors('franchise_number');
    $this->postJson(route('franchise.update', $franchise), [...$payload, 'expiration_date' => '2025-01-01'])->assertUnprocessable()->assertJsonValidationErrors('expiration_date');
    $this->postJson(route('franchise.update', $franchise), [...$payload, 'date_issued' => null, 'expiration_date' => null])->assertOk();
    expect($franchise->fresh()->date_issued)->toBeNull();
});

test('franchise editing requires page access', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-EDIT']);
    $this->postJson(route('franchise.update', $franchise), [])->assertUnauthorized();
    $other = User::create(['name' => 'Other', 'username' => 'noaccess', 'password' => 'password']);
    $this->actingAs($other)->postJson(route('franchise.update', $franchise), [])->assertForbidden();
});

test('page viewers can create franchises with validated fields', function () {
    $data = ['franchise_number' => '  F-NEW  ', 'status' => 'active', 'date_issued' => '2026-01-01', 'expiration_date' => '2027-01-01'];
    $this->actingAs($this->viewer)->postJson(route('franchise.store'), $data)->assertCreated()->assertJsonPath('message', 'Franchise created.');
    $this->assertDatabaseHas('franchises', ['franchise_number' => 'F-NEW', 'status' => 'active']);
    $this->postJson(route('franchise.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('franchise_number');
    $this->postJson(route('franchise.store'), [...$data, 'franchise_number' => 'F-OTHER', 'expiration_date' => '2025-01-01'])->assertUnprocessable()->assertJsonValidationErrors('expiration_date');
    $this->postJson(route('franchise.store'), ['franchise_number' => '   ', 'status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['franchise_number', 'status']);
    $this->postJson(route('franchise.store'), ['franchise_number' => 'F-NODATES', 'status' => 'unavailable', 'date_issued' => null, 'expiration_date' => null])->assertCreated();
});

test('creating franchises requires page access', function () {
    $this->postJson(route('franchise.store'), [])->assertUnauthorized();
    $other = User::create(['name' => 'Other', 'username' => 'no-create-access', 'password' => 'password']);
    $this->actingAs($other)->postJson(route('franchise.store'), [])->assertForbidden();
});

test('transaction history is scoped paginated and protected by page permission', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-HISTORY']);
    $other = Franchise::create(['franchise_number' => 'F-OTHER']);
    foreach (range(1, 12) as $index) {
        $franchise->transactions()->create(['transaction_type' => 'renewal', 'date_filed' => '2026-11-17']);
    }
    $other->transactions()->create(['transaction_type' => 'application', 'date_filed' => '2026-11-17']);
    $this->getJson(route('franchise.transactions', $franchise))->assertUnauthorized();
    $this->actingAs($this->viewer)->getJson(route('franchise.transactions', $franchise))->assertOk()
        ->assertJsonPath('transactions.total', 12)->assertJsonCount(10, 'transactions.data')
        ->assertJsonPath('transactions.data.0.franchise_id', $franchise->id);
    $this->getJson(route('franchise.transactions', ['franchise' => $franchise, 'page' => 2]))->assertOk()->assertJsonCount(2, 'transactions.data');
    $this->getJson(route('franchise.transactions', 999999))->assertNotFound();
    $user = User::create(['name' => 'Other', 'username' => 'no-history', 'password' => 'password']);
    $this->actingAs($user)->getJson(route('franchise.transactions', $franchise))->assertForbidden();
});

test('adding a transaction binds it to the selected franchise and starts pending', function () {
    $franchise = Franchise::create(['franchise_number' => 'F-TX']);
    $other = Franchise::create(['franchise_number' => 'F-OTHER-TX']);
    $data = ['transaction_type' => 'application', 'date_filed' => '2026-11-17', 'remarks' => 'Renewal request', 'details' => ['confirmed' => true], 'franchise_id' => $other->id, 'status' => 'approved', 'approved_at' => '2026-11-17'];
    $this->actingAs($this->viewer)->postJson(route('franchise.transactions.store', $franchise), $data)->assertCreated();
    $transaction = $franchise->transactions()->sole();
    expect($transaction->status)->toBe('pending')->and($transaction->approved_at)->toBeNull()->and($transaction->processed_by)->toBeNull();
    expect($other->transactions()->count())->toBe(0);
    $this->postJson(route('franchise.transactions.store', $franchise), ['transaction_type' => 'invalid', 'date_filed' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['transaction_type']);
    $this->postJson(route('franchise.transactions.store', 999999), $data)->assertNotFound();
    $user = User::create(['name' => 'Other', 'username' => 'no-add-transaction', 'password' => 'password']);
    $this->actingAs($user)->postJson(route('franchise.transactions.store', $franchise), $data)->assertForbidden();
});
