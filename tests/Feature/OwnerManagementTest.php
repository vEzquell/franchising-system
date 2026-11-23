<?php

use App\Models\Attachment;
use App\Models\Franchise;
use App\Models\FranchiseOwner;
use App\Models\Liner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
    $this->getJson(route('owners.name-matches', ['first_name' => 'Ana']))->assertUnauthorized();
    $this->postJson(route('owners.store'), [])->assertUnauthorized();
    $other = User::create(['name' => 'Other', 'username' => 'owner-other', 'password' => 'password']);
    $this->actingAs($other)->getJson(route('owners.index'))->assertForbidden();
    $this->getJson(route('owners.name-matches', ['first_name' => 'Ana']))->assertForbidden();
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

test('owner name lookup returns possible matches without creating an owner', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'address' => 'Main Street', 'contact_number' => '09123456789']);
    FranchiseOwner::create(['first_name' => 'Anabel', 'last_name' => 'Reyes']);
    FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Cruz']);

    $this->actingAs($this->staff)->getJson(route('owners.name-matches', ['first_name' => 'AN']))->assertOk()->assertJsonCount(2, 'owners');
    $this->getJson(route('owners.name-matches', ['last_name' => 'cr']))->assertOk()->assertJsonCount(2, 'owners');
    $this->getJson(route('owners.name-matches', ['first_name' => 'AN', 'last_name' => 'cr']))->assertOk()
        ->assertJsonCount(1, 'owners')
        ->assertJsonPath('owners.0.id', $owner->id)
        ->assertJsonPath('owners.0.address', 'Main Street')
        ->assertJsonMissingPath('owners.0.contact_number');
    $this->getJson(route('owners.name-matches', ['first_name' => 'A']))->assertOk()->assertJsonCount(0, 'owners');
    $this->getJson(route('owners.name-matches', ['first_name' => 'A%']))->assertOk()->assertJsonCount(0, 'owners');
    $this->getJson(route('owners.name-matches', ['last_name' => str_repeat('x', 51)]))->assertUnprocessable()->assertJsonValidationErrors('last_name');
    $this->assertDatabaseCount('franchise_owners', 3);
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
    $issuedAt = today()->subWeeks(2);
    $franchise = Franchise::create(['franchise_number' => 'F-ASSIGN', 'date_issued' => $issuedAt]);
    $this->actingAs($this->staff)->getJson(route('owners.franchises', ['search' => 'ASSIGN']))->assertOk()
        ->assertJsonPath('franchises.0.id', $franchise->id)->assertJsonPath('franchises.0.date_issued', $issuedAt->toJSON());
    $this->assertDatabaseCount('franchise_ownerships', 0);
    $this->postJson(route('owners.franchises.store', $owner), ['franchise_id' => $franchise->id, 'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString()])->assertCreated();
    $this->assertDatabaseHas('franchise_ownerships', ['franchise_owner_id' => $owner->id, 'franchise_id' => $franchise->id, 'ended_at' => null]);
    expect($owner->ownerships()->sole()->started_at->toDateString())->toBe($issuedAt->toDateString());
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
    $this->getJson(route('transactions.options', $franchise))->assertOk()->assertJsonPath('current_owner_ids.0', $owner->id);
});

test('assignment preserves existing ownership and rejects invalid dates', function () {
    $owner = FranchiseOwner::create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
    $other = FranchiseOwner::create(['first_name' => 'Ben', 'last_name' => 'Reyes']);
    $franchise = Franchise::create(['franchise_number' => 'F-ASSIGN']);
    $data = ['franchise_id' => $franchise->id, 'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString()];
    $this->actingAs($this->staff)->postJson(route('owners.franchises.store', $owner), [...$data, 'started_at' => null])
        ->assertUnprocessable()->assertJsonValidationErrors('started_at');
    $this->postJson(route('owners.franchises.store', $owner), [...$data, 'started_at' => today()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('started_at');
    expect($franchise->fresh()->date_issued)->toBeNull();
    $this->postJson(route('owners.franchises.store', $owner), $data)->assertCreated();
    expect($franchise->fresh()->date_issued->toDateString())->toBe($data['started_at']);
    expect($franchise->ownerships()->sole()->started_at->toDateString())->toBe($data['started_at']);
    $this->postJson(route('owners.franchises.store', $other), $data)->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
    $this->postJson(route('owners.franchises.store', $owner), $data)->assertUnprocessable()->assertJsonValidationErrors('franchise_id');
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
    $this->postJson(route('owners.franchises.store', $owner), ['franchise_id' => $franchise->id, 'expiration_date' => today()->addYear()->toDateString()])->assertCreated();
    expect($franchise->fresh()->expiration_date->toDateString())->toBe(today()->addYear()->toDateString());
    $this->assertDatabaseCount('franchise_ownerships', 1);
});

test('owner setup links a franchise, vehicle, route and ownership documents in one submission', function (bool $existingOwner) {
    Storage::fake('local');
    $this->staff->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
    $this->staff->givePermissionTo(Permission::create(['name' => 'view ownerships', 'guard_name' => 'web']));
    $issuedAt = today()->subMonth()->toDateString();
    $franchise = Franchise::create(['franchise_number' => 'F-SETUP', 'date_issued' => $issuedAt]);
    $liner = Liner::create(['name' => 'Central Liner', 'route' => 'Central Terminal']);
    $ownerDetails = ['first_name' => 'Ana', 'last_name' => 'Cruz'];
    $owner = $existingOwner ? FranchiseOwner::create($ownerDetails) : null;
    $url = $owner ? route('owners.franchises.store', $owner) : route('owners.store');
    $this->actingAs($this->staff)->getJson(route('owners.franchises.setup-options', $franchise))->assertOk()
        ->assertJsonPath('current_vehicle', null)->assertJsonPath('liners.0.route', 'Central Terminal');

    $this->post($url, [
        ...($owner ? [] : $ownerDetails), 'franchise_id' => $franchise->id,
        'expiration_date' => today()->addYear()->toDateString(), 'liner_id' => $liner->id,
        'vehicle' => ['mode' => 'new', 'make_type' => 'Motorcycle', 'plate_number' => 'SET-001'],
        'attachments' => [['document_name' => 'Franchise certificate', 'file' => UploadedFile::fake()->create('certificate.pdf', 10, 'application/pdf')]],
    ], ['Accept' => 'application/json'])->assertCreated();

    $ownership = FranchiseOwner::sole()->ownerships()->sole();
    if ($owner) {
        expect($ownership->owner->is($owner))->toBeTrue();
    }
    $assignment = $franchise->vehicleAssignments()->sole();
    $attachment = $ownership->attachments()->sole();
    expect($ownership->started_at->toDateString())->toBe($issuedAt)
        ->and($assignment->started_at->toDateString())->toBe($issuedAt)
        ->and($assignment->vehicle->plate_number)->toBe('SET-001')
        ->and($franchise->fresh()->liner->is($liner))->toBeTrue();
    Storage::disk('local')->assertExists($attachment->path);
    $this->getJson(route('owners.franchises.setup-options', $franchise))->assertOk()
        ->assertJsonPath('current_vehicle.id', $assignment->vehicle_id)->assertJsonPath('liner_id', $liner->id);
})->with(['new owner' => [false], 'existing owner' => [true]]);

test('owner setup honors permissions for route, vehicle and attachment changes', function (bool $existingOwner) {
    $franchise = Franchise::create(['franchise_number' => 'F-PERMISSION']);
    $ownerDetails = ['first_name' => 'Ana', 'last_name' => 'Cruz'];
    $owner = $existingOwner ? FranchiseOwner::create($ownerDetails) : null;
    $url = $owner ? route('owners.franchises.store', $owner) : route('owners.store');
    $data = [...($owner ? [] : $ownerDetails), 'franchise_id' => $franchise->id, 'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString()];
    $this->actingAs($this->staff)->getJson(route('owners.franchises.setup-options', $franchise))->assertForbidden();
    $this->postJson($url, [...$data, 'liner_id' => 1])->assertForbidden();
    $this->postJson($url, [...$data, 'vehicle' => ['mode' => 'new']])->assertForbidden();
    $this->postJson($url, [...$data, 'attachments' => []])->assertForbidden();
    $this->assertDatabaseCount('franchise_owners', $existingOwner ? 1 : 0);
    $this->assertDatabaseCount('franchise_ownerships', 0);
    $this->postJson($url, $data)->assertCreated();
})->with(['new owner' => [false], 'existing owner' => [true]]);

test('an attachment failure rolls back the owner setup and removes uploaded files', function (bool $existingOwner) {
    Storage::fake('local');
    $this->staff->givePermissionTo(Permission::create(['name' => 'view franchise', 'guard_name' => 'web']));
    $this->staff->givePermissionTo(Permission::create(['name' => 'view ownerships', 'guard_name' => 'web']));
    $franchise = Franchise::create(['franchise_number' => 'F-ROLLBACK']);
    $liner = Liner::create(['name' => 'West Liner', 'route' => 'West Terminal']);
    $ownerDetails = ['first_name' => 'Ana', 'last_name' => 'Cruz'];
    $owner = $existingOwner ? FranchiseOwner::create($ownerDetails) : null;
    $url = $owner ? route('owners.franchises.store', $owner) : route('owners.store');
    Attachment::creating(function (Attachment $attachment): void {
        if ($attachment->document_name === 'Second document') {
            throw new RuntimeException('Simulated attachment save failure.');
        }
    });

    try {
        $this->actingAs($this->staff)->post($url, [
            ...($owner ? [] : $ownerDetails), 'franchise_id' => $franchise->id,
            'started_at' => today()->toDateString(), 'expiration_date' => today()->addYear()->toDateString(), 'liner_id' => $liner->id,
            'vehicle' => ['mode' => 'new', 'make_type' => 'Motorcycle', 'plate_number' => 'SET-002'],
            'attachments' => [
                ['document_name' => 'First document', 'file' => UploadedFile::fake()->create('first.pdf', 10, 'application/pdf')],
                ['document_name' => 'Second document', 'file' => UploadedFile::fake()->create('second.pdf', 10, 'application/pdf')],
            ],
        ], ['Accept' => 'application/json'])->assertServerError();
    } finally {
        Attachment::flushEventListeners();
    }

    $this->assertDatabaseCount('franchise_owners', $existingOwner ? 1 : 0);
    foreach (['franchise_ownerships', 'vehicles', 'franchise_vehicle_assignments', 'attachments'] as $table) {
        $this->assertDatabaseCount($table, 0);
    }
    $this->assertDatabaseHas('franchises', ['id' => $franchise->id, 'date_issued' => null, 'expiration_date' => null, 'liner_id' => null]);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['new owner' => [false], 'existing owner' => [true]]);
