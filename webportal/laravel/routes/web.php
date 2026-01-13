<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AdminAuthController;
use App\Http\Controllers\Auth\UserAuthController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\CarrierController;
use App\Http\Controllers\DialerController;

Route::get('/', function () {
    return redirect()->route('user.login');
});

Route::get('/admin/login', [AdminAuthController::class, 'showLogin'])->name('admin.login');
Route::post('/admin/login', [AdminAuthController::class, 'login'])->name('admin.login.submit');
Route::get('/admin/forgot-password', [PasswordResetController::class, 'showAdminForgot'])->name('admin.password.forgot');
Route::post('/admin/forgot-password', [PasswordResetController::class, 'sendAdminOtp'])->name('admin.password.send');
Route::get('/admin/reset-password', [PasswordResetController::class, 'showAdminReset'])->name('admin.password.reset');
Route::post('/admin/reset-password', [PasswordResetController::class, 'resetAdminPassword'])->name('admin.password.update');

Route::get('/login', [UserAuthController::class, 'showLogin'])->name('user.login');
Route::post('/login', [UserAuthController::class, 'login'])->name('user.login.submit');
Route::get('/forgot-password', [PasswordResetController::class, 'showUserForgot'])->name('password.forgot');
Route::post('/forgot-password', [PasswordResetController::class, 'sendUserOtp'])->name('password.send');
Route::get('/reset-password', [PasswordResetController::class, 'showUserReset'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'resetUserPassword'])->name('password.update');

Route::middleware('admin.auth')->group(function () {
    Route::get('/admin/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/admin/users/create', [UserController::class, 'create'])->name('admin.users.create');
    Route::post('/admin/users', [UserController::class, 'store'])->name('admin.users.store');
    Route::get('/admin/users/{user}/edit', [UserController::class, 'edit'])->name('admin.users.edit');
    Route::put('/admin/users/{user}', [UserController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy'])->name('admin.users.destroy');
    Route::get('/admin/carriers', [CarrierController::class, 'index'])->name('admin.carriers.index');
    Route::post('/admin/carriers', [CarrierController::class, 'store'])->name('admin.carriers.store');
    Route::get('/admin/carriers/{carrier}/edit', [CarrierController::class, 'edit'])->name('admin.carriers.edit');
    Route::put('/admin/carriers/{carrier}', [CarrierController::class, 'update'])->name('admin.carriers.update');
    Route::delete('/admin/carriers/{carrier}', [CarrierController::class, 'destroy'])->name('admin.carriers.destroy');
    Route::post('/admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');
});

Route::middleware('user.auth')->group(function () {
    Route::get('/dialer', [DialerController::class, 'index'])->name('dialer.index');
    Route::post('/dialer', [DialerController::class, 'dial'])->name('dialer.dial');
    Route::get('/dialer/session/{uuid}', [DialerController::class, 'session'])->name('dialer.session');
    Route::get('/dialer/calls/{uuid}/status', [DialerController::class, 'callStatus'])->name('dialer.status');
    Route::post('/dialer/calls/{uuid}/{action}', [DialerController::class, 'control'])->name('dialer.control');
    Route::post('/logout', [UserAuthController::class, 'logout'])->name('user.logout');
});
