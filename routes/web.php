<?php

use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
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

    // Role Management
    Route::get('/roles', [RoleController::class, 'index'])
        ->middleware('can:view roles')
        ->name('roles.index');
    Route::get('/roles/create', fn () => to_route('roles.index', ['create' => 1]))->middleware('can:view roles')->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->middleware('can:view roles')->name('roles.store');
    Route::post('/roles/{role}', [RoleController::class, 'update'])->middleware('can:view roles')->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->middleware('can:view roles')->name('roles.destroy');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

});
