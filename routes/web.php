<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\AdminViewController;
use App\Http\Controllers\Admin\UserController;


Route::get('/', [AuthController::class, 'viewLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');

Route::middleware('auth')->group(function () {

    // Dashboard
    Route::get('/dashboard', [AdminViewController::class, 'dashboard'])
        ->name('dashboard');
    
    // User Management
    Route::get('/users', [UserController::class, 'users'])
        ->name('users');

    // Logout
    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

});