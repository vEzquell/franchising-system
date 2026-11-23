<?php

use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Admin\FranchiseController;
use App\Http\Controllers\Admin\FranchiseTransactionController;
use App\Http\Controllers\Admin\LinerController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\OwnershipController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RequirementController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\TransactionTypeController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VehicleAssignmentController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AuthController::class, 'viewLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminViewController::class, 'dashboard'])
        ->middleware('can:view dashboard')
        ->name('dashboard');

    // User Management
    Route::get('/users', [UserController::class, 'users'])
        ->middleware('can:view users')
        ->name('users');
    Route::get('/users/create', fn () => to_route('users', ['create' => 1]))->middleware('can:view users')->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->middleware('can:view users')->name('users.store');
    Route::post('/users/{user}', [UserController::class, 'update'])->middleware('can:view users')->name('users.update');

    // Permission Management
    Route::get('/permissions', [PermissionController::class, 'index'])
        ->middleware('can:view permissions')
        ->name('permissions.index');
    Route::post('/permissions', [PermissionController::class, 'store'])->middleware('can:view permissions')->name('permissions.store');
    Route::get('/permissions/create', fn () => to_route('permissions.index', ['create' => 1]))
        ->middleware('can:view permissions')->name('permissions.create');
    Route::post('/permissions/{permission}', [PermissionController::class, 'update'])->middleware('can:view permissions')->name('permissions.update');
    Route::delete('/permissions/{permission}', [PermissionController::class, 'destroy'])->middleware('can:view permissions')->name('permissions.destroy');
    Route::get('/permissions/{permission}/users', [PermissionController::class, 'users'])
        ->middleware('can:view permissions')->name('permissions.users.index');
    Route::put('/permissions/{permission}/users/{user}', [PermissionController::class, 'updateUser'])
        ->middleware('can:view permissions')->name('permissions.users.update');

    // Role Management
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('can:view roles')
        ->name('roles.index');
    Route::get('/roles/create', fn () => to_route('roles.index', ['create' => 1]))->middleware('can:view roles')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:view roles')->name('roles.store');
    Route::post('/roles/{role}', [RoleController::class, 'update'])->middleware('can:view roles')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:view roles')->name('roles.destroy');

    // Franchise Management
    Route::get('/franchise', [FranchiseController::class, 'index'])
        ->middleware('can:view franchise')
        ->name('franchise.index');
    Route::get('/franchise/{franchise}/transactions/options', [FranchiseTransactionController::class, 'options'])
        ->middleware('can:view franchise')->name('franchise.transactions.options');
    Route::post('/franchise/{franchise}/transactions', [FranchiseTransactionController::class, 'store'])
        ->middleware('can:view franchise')->name('franchise.transactions.store');
    Route::get('/franchise/{franchise}/transactions', [FranchiseController::class, 'transactions'])
        ->middleware('can:view franchise')->name('franchise.transactions');
    Route::get('/franchise/{franchise}/vehicles', [VehicleAssignmentController::class, 'options'])->middleware('can:view franchise')->name('franchise.vehicles');
    Route::post('/franchise/{franchise}/vehicles', [VehicleAssignmentController::class, 'store'])->middleware('can:view franchise')->name('franchise.vehicles.store');
    Route::get('/franchise/{franchise}/liner', [FranchiseController::class, 'linerOptions'])
        ->middleware('can:view franchise')->name('franchise.liner');
    Route::patch('/franchise/{franchise}/liner', [FranchiseController::class, 'updateLiner'])
        ->middleware('can:view franchise')->name('franchise.liner.update');
    Route::post('/franchise', [FranchiseController::class, 'store'])
        ->middleware('can:view franchise')->name('franchise.store');
    Route::post('/franchise/{franchise}', [FranchiseController::class, 'update'])
        ->middleware('can:view franchise')->name('franchise.update');

    // Transaction Management
    Route::middleware('can:view transactions')->prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [FranchiseTransactionController::class, 'index'])->name('index');
        Route::get('/franchises', [FranchiseTransactionController::class, 'franchises'])->name('franchises');
        Route::get('/franchises/{franchise}/options', [FranchiseTransactionController::class, 'options'])->name('options');
        Route::post('/', [FranchiseTransactionController::class, 'storeSelected'])->name('store');
        Route::post('/filings', [FranchiseTransactionController::class, 'file'])->name('file');
        Route::get('/{transaction}', [FranchiseTransactionController::class, 'show'])->name('show');
        Route::patch('/{transaction}/status', [FranchiseTransactionController::class, 'updateStatus'])
            ->middleware('can:process transactions')->name('status.update');
        Route::get('/{transaction}/attachments/{attachment}', [FranchiseTransactionController::class, 'download'])->name('attachments.download');
    });
    Route::middleware('can:process transactions')->prefix('requirements')->name('requirements.')->group(function () {
        Route::get('/', [RequirementController::class, 'index'])->name('index');
        Route::post('/', [RequirementController::class, 'store'])->name('store');
        Route::post('/{requirement}', [RequirementController::class, 'update'])->name('update');
    });

    Route::middleware('can:process transactions')->prefix('transaction-types')->name('transaction-types.')->group(function () {
        Route::get('/', [TransactionTypeController::class, 'index'])->name('index');
        Route::post('/', [TransactionTypeController::class, 'store'])->name('store');
        Route::post('/{transactionType}', [TransactionTypeController::class, 'update'])->name('update');
    });

    // Ownership records and historical documents
    Route::middleware('can:view ownerships')->prefix('ownerships')->name('ownerships.')->group(function () {
        Route::get('/', [OwnershipController::class, 'index'])->name('index');
        Route::get('/{ownership}', [OwnershipController::class, 'show'])->name('show');
        Route::post('/{ownership}/attachments', [OwnershipController::class, 'storeAttachments'])->name('attachments.store');
        Route::get('/{ownership}/attachments/{attachment}', [OwnershipController::class, 'download'])->name('attachments.download');
        Route::delete('/{ownership}/attachments/{attachment}', [OwnershipController::class, 'destroyAttachment'])->name('attachments.destroy');
    });
    // Owner Management
    Route::get('/owners', [OwnerController::class, 'index'])->middleware('can:view owners')->name('owners.index');
    Route::get('/owners/name-matches', [OwnerController::class, 'nameMatches'])->middleware('can:view owners')->name('owners.name-matches');
    Route::get('/owners/franchises', [OwnerController::class, 'franchises'])->middleware('can:view owners')->name('owners.franchises');
    Route::get('/owners/franchises/{franchise}/setup-options', [OwnerController::class, 'setupOptions'])
        ->middleware(['can:view owners', 'can:view franchise'])->name('owners.franchises.setup-options');
    Route::post('/owners/{owner}/franchises', [OwnerController::class, 'assignFranchise'])->middleware('can:view owners')->name('owners.franchises.store');
    Route::post('/owners', [OwnerController::class, 'store'])->middleware('can:view owners')->name('owners.store');
    Route::post('/owners/{owner}', [OwnerController::class, 'update'])->middleware('can:view owners')->name('owners.update');

    Route::middleware('can:view liners')->prefix('liners')->name('liners.')->group(function () {
        Route::get('/', [LinerController::class, 'index'])->name('index');
        Route::post('/', [LinerController::class, 'store'])->name('store');
        Route::post('/{liner}', [LinerController::class, 'update'])->name('update');
    });

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

});
