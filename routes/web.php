<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

Route::get('/', function () {
    return redirect('/login');
});

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard/inbox', function () {
        return view('dashboard');
    })->name('dashboard.inbox');

    Route::get('/dashboard/settings', function () {
        return view('dashboard');
    })->name('dashboard.settings');

    Route::get('/dashboard/users', function () {
        return view('dashboard');
    })->name('dashboard.users');

    Route::get('/dashboard', function () {
        return redirect()->route('dashboard.inbox');
    })->name('dashboard');
});
