<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\MemberRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MotorcycleController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\OrganizationUserController;
use App\Http\Controllers\InscriptionController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\PublicInscriptionController;
use App\Http\Controllers\RoleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $user = auth()->user();

    if (! $user) {
        return redirect()->route('login');
    }

    if ($user->isSuperAdmin()) {
        return redirect()->route('organizations.index');
    }

    $organization = $user->currentOrganization
        ?? $user->organizationRoles()->with('organization')->first()?->organization;

    return $organization
        ? redirect()->route('dashboard', $organization)
        : redirect()->route('organizations.index');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:10,1');
});

Route::prefix('/i/{token}')->group(function (): void {
    Route::get('/', [PublicInscriptionController::class, 'show'])->name('public.inscription.show');
    Route::get('/datos', [PublicInscriptionController::class, 'editBasics'])->name('public.inscription.basics.edit');
    Route::post('/datos', [PublicInscriptionController::class, 'storeBasics'])->middleware('throttle:inscription-store')->name('public.inscription.basics');
    Route::post('/documentos', [PublicInscriptionController::class, 'upload'])->middleware('throttle:inscription-upload')->name('public.inscription.upload');
    Route::get('/documentos/{step}/vista', [PublicInscriptionController::class, 'preview'])->name('public.inscription.preview');
    Route::post('/continuar', [PublicInscriptionController::class, 'continueToForm'])->middleware('throttle:inscription-store')->name('public.inscription.continue');
    Route::post('/enviar', [PublicInscriptionController::class, 'submitQuick'])->middleware('throttle:inscription-store')->name('public.inscription.quick');
    Route::get('/formulario', [PublicInscriptionController::class, 'form'])->name('public.inscription.form');
    Route::post('/formulario', [PublicInscriptionController::class, 'store'])->middleware('throttle:inscription-store')->name('public.inscription.store');
    Route::get('/gracias', [PublicInscriptionController::class, 'thanks'])->name('public.inscription.thanks');
    Route::post('/consultar', [PublicInscriptionController::class, 'lookup'])->middleware('throttle:inscription-store')->name('public.inscription.lookup');
    Route::get('/solicitud', [PublicInscriptionController::class, 'request'])->name('public.inscription.request');
    Route::put('/solicitud', [PublicInscriptionController::class, 'updateRequest'])->middleware('throttle:inscription-store')->name('public.inscription.request.update');
    Route::post('/solicitud/documentos', [PublicInscriptionController::class, 'updateRequestDocument'])->middleware('throttle:inscription-upload')->name('public.inscription.request.documents');
    Route::get('/solicitud/documentos/{document}/archivos/{file}', [PublicInscriptionController::class, 'downloadRequestFile'])->name('public.inscription.request.file');
});

Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/registro', [MemberRegistrationController::class, 'create'])->name('register.member');
    Route::post('/registro', [MemberRegistrationController::class, 'store'])->name('register.member.store');
});

Route::middleware(['auth', 'member.profile'])->group(function (): void {
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
            Route::put('/users/{user}', [OrganizationUserController::class, 'update'])->middleware('permission:users.update')->name('users.update');

            Route::get('/inscripciones', [InscriptionController::class, 'index'])->middleware('permission:inscriptions.view')->name('inscriptions.index');
            Route::post('/inscripciones/links', [InscriptionController::class, 'store'])->middleware('permission:inscriptions.create')->name('inscriptions.links.store');
            Route::post('/inscripciones/links/{inscriptionLink}/desactivar', [InscriptionController::class, 'deactivate'])->middleware('permission:inscriptions.create')->name('inscriptions.links.deactivate');
            Route::delete('/inscripciones/links/{inscriptionLink}', [InscriptionController::class, 'destroy'])->middleware('permission:inscriptions.create')->name('inscriptions.links.destroy');
            Route::get('/inscripciones/{application}', [InscriptionController::class, 'show'])->middleware('permission:inscriptions.view')->name('inscriptions.show');
            Route::put('/inscripciones/{application}', [InscriptionController::class, 'update'])->middleware('permission:inscriptions.verify')->name('inscriptions.update');
            Route::post('/inscripciones/{application}/documentos', [InscriptionController::class, 'storeDocument'])->middleware('permission:inscriptions.verify')->name('inscriptions.documents.store');
            Route::post('/inscripciones/{application}/verificar', [InscriptionController::class, 'verify'])->middleware('permission:inscriptions.verify')->name('inscriptions.verify');
            Route::post('/inscripciones/{application}/acceso', [InscriptionController::class, 'grantAccess'])->middleware('permission:inscriptions.verify')->name('inscriptions.access');

            Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view')->name('roles.index');
            Route::get('/roles/create', [RoleController::class, 'create'])->middleware('permission:roles.create')->name('roles.create');
            Route::post('/roles', [RoleController::class, 'store'])->middleware('permission:roles.create')->name('roles.store');
            Route::put('/roles/{role}', [RoleController::class, 'update'])->middleware('permission:roles.update')->name('roles.update');
        });
});
