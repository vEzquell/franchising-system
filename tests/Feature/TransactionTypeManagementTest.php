<?php

use App\Models\Franchise;
use App\Models\Requirement;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->staff = User::create(['name' => 'Staff', 'username' => 'transaction-types-staff', 'password' => 'password']);
    $this->staff->givePermissionTo(Permission::create(['name' => 'view transactions', 'guard_name' => 'web']));
    $this->processPermission = Permission::create(['name' => 'process transactions', 'guard_name' => 'web']);
});

test('transaction processors can manage built-in and general transaction types', function () {
    $this->get(route('transaction-types.index'))->assertRedirect(route('login'));
    $this->actingAs($this->staff)->get(route('transaction-types.index'))->assertForbidden();
    $this->postJson(route('transaction-types.store'), ['name' => 'Duplicate copy', 'is_active' => true])->assertForbidden();

    $this->staff->givePermissionTo($this->processPermission);
    $this->get(route('transaction-types.index'))->assertInertia(fn (Assert $page) => $page
        ->component('Admin/TransactionTypes/Index')->has('transactionTypes', 4)->has('builtInCodes', 4));

    $payload = ['name' => 'Certified copy', 'is_active' => true, 'code' => 'renewal'];
    $code = $this->postJson(route('transaction-types.store'), $payload)->assertCreated()
        ->assertJsonPath('transactionType.name', 'Certified copy')->json('transactionType.code');
    expect($code)->not->toBe('renewal');
    $this->postJson(route('transaction-types.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('name');
    $this->postJson(route('transaction-types.update', $code), ['name' => 'Certified copy', 'is_active' => false])
        ->assertOk()->assertJsonPath('transactionType.code', $code)->assertJsonPath('transactionType.is_active', false);
});

test('general types support requirements, filing, and approval after deactivation', function () {
    Storage::fake('local');
    $this->staff->givePermissionTo($this->processPermission);
    $this->actingAs($this->staff);
    $type = TransactionType::factory()->create(['name' => 'Certified copy']);
    $franchise = Franchise::create(['franchise_number' => 'F-TYPE', 'date_issued' => '2025-01-10', 'expiration_date' => '2026-01-10']);
    $this->postJson(route('requirements.store'), ['transaction_type' => $type->code, 'name' => 'Valid ID'])->assertCreated();
    expect(Requirement::sole()->type->is($type))->toBeTrue();

    $this->getJson(route('transactions.index'))->assertOk()->assertJsonFragment(['value' => $type->code, 'label' => 'Certified copy', 'workflow' => 'general']);
    $this->getJson(route('transactions.options', $franchise))->assertOk()->assertJsonPath('requirements.0.transaction_type', $type->code);

    $payload = ['franchise_id' => $franchise->id, 'transaction_type' => $type->code, 'remarks' => 'For filing'];
    $this->postJson(route('transactions.store'), [...$payload, 'certificate_issue_date' => '2026-01-01'])
        ->assertUnprocessable()->assertJsonValidationErrors('certificate_issue_date');
    $this->postJson(route('transactions.store'), [...$payload, 'attachments' => [[
        'document_name' => 'ID', 'file' => UploadedFile::fake()->create('id.pdf', 1, 'application/pdf'),
    ]]])->assertCreated();
    $transaction = $franchise->transactions()->sole();
    Storage::disk('local')->assertExists($transaction->attachments()->sole()->path);
    expect($transaction->type->is($type))->toBeTrue();

    $this->postJson(route('transaction-types.update', $type), ['name' => 'Certified copy', 'is_active' => false])->assertOk();
    $this->postJson(route('transactions.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('transaction_type');
    $this->getJson(route('transactions.index'))->assertOk()->assertJsonCount(4, 'transactionTypes')
        ->assertJsonPath('transactions.data.0.type.name', 'Certified copy');
    $this->getJson(route('transactions.show', $transaction))->assertOk()
        ->assertJsonPath('transaction.type.name', 'Certified copy')->assertJsonPath('transaction.remarks', 'For filing');

    $this->patchJson(route('transactions.status.update', $transaction), ['status' => 'approved'])->assertOk();
    expect($transaction->fresh()->status)->toBe('approved');
    expect($franchise->fresh()->date_issued->toDateString())->toBe('2025-01-10');
    expect($franchise->fresh()->expiration_date->toDateString())->toBe('2026-01-10');
});
