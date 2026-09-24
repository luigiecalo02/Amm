<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('organizations.index');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/organizations', [OrganizationController::class, 'index'])->name('organizations.index');
    Route::post('/organizations', [OrganizationController::class, 'store'])->name('organizations.store');

    Route::prefix('/app/{organization}')
        ->middleware(['current.organization', 'organization'])
        ->group(function (): void {
            Route::get('/', DashboardController::class)->name('dashboard');

            Route::get('/settings', [OrganizationController::class, 'edit'])->name('organizations.edit');
            Route::put('/settings', [OrganizationController::class, 'update'])->name('organizations.update');

            Route::get('/people', [PersonController::class, 'index'])->middleware('permission:people.view')->name('people.index');
            Route::get('/people/create', [PersonController::class, 'create'])->middleware('permission:people.create')->name('people.create');
            Route::post('/people', [PersonController::class, 'store'])->middleware('permission:people.create')->name('people.store');
            Route::get('/people/{person}', [PersonController::class, 'show'])->middleware('permission:people.view')->name('people.show');
            Route::get('/people/{person}/edit', [PersonController::class, 'edit'])->middleware('permission:people.update')->name('people.edit');
            Route::put('/people/{person}', [PersonController::class, 'update'])->middleware('permission:people.update')->name('people.update');

            Route::get('/members', [MemberController::class, 'index'])->middleware('permission:members.view')->name('members.index');
            Route::post('/members', [MemberController::class, 'store'])->middleware('permission:members.create')->name('members.store');

            Route::get('/motorcycles', [MotorcycleController::class, 'index'])->middleware('permission:motorcycles.view')->name('motorcycles.index');
            Route::post('/motorcycles', [MotorcycleController::class, 'store'])->middleware('permission:motorcycles.create')->name('motorcycles.store');

            Route::get('/documents', [DocumentController::class, 'index'])->middleware('permission:documents.view')->name('documents.index');
            Route::get('/documents/expiring', [DocumentController::class, 'expiring'])->middleware('permission:reports.view')->name('documents.expiring');
            Route::post('/documents', [DocumentController::class, 'store'])->middleware('permission:documents.create')->name('documents.store');
            Route::post('/documents/scans', [DocumentController::class, 'scan'])->middleware('permission:documents.scan')->name('documents.scan');
            Route::get('/documents/{document}/files/{file}', [DocumentController::class, 'download'])->middleware('permission:documents.download')->name('documents.download');

            Route::get('/users', [OrganizationUserController::class, 'index'])->middleware('permission:users.view')->name('users.index');
            Route::post('/users', [OrganizationUserController::class, 'store'])->middleware('permission:users.create')->name('users.store');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        });
});
