<?php

use App\Http\Controllers\PDF\BlankPA;
use App\Http\Controllers\PDF\PA;
use Illuminate\Support\Facades\Route;

Route::get('/pa/{franchise}', [PA::class, 'generatepa'])
    ->middleware(['auth', 'can:view franchise'])
    ->name('pa');

Route::get('/transactions/{transaction}/pa', [PA::class, 'forTransaction'])
    ->middleware(['auth', 'can:view transactions', 'can:view franchise'])
    ->name('transactions.pa');

Route::get('/blankPA', [BlankPA::class, 'generatepa']);
