<?php

use App\Http\Controllers\ActivityLogsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\ClientEditController;
use App\Http\Controllers\ClientListController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\DuplicateReviewController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PermissionsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionEventsController;
use App\Http\Controllers\TransactionRequirementController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::group(['namespace' => 'App\Http\Controllers\Auth'],function()
{
    // ----------------------------- login ------------------------------------//
    Route::get('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('auth/azure/redirect', [LoginController::class, 'redirectToAzure'])->name('azure.redirect');
    Route::get('auth/azure/callback', [LoginController::class, 'handleAzureCallback'])->name('azure.callback');

});

Route::group(['namespace' => 'App\Http\Controllers'], function () {
    Route::middleware('auth')->group(function () {
        Route::middleware('viewer.readonly')->group(function () {
        // --------------------- Dashboard ------------------//
        Route::get('dashboard', [ProfileController::class, 'dashboard'])->name('dashboard');

        // --------------------- Activity Logs ------------------//
        Route::get('activity-logs', [ActivityLogsController::class, 'index'])->name('activity.logs');

        // --------------------- Users ------------------//
        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::put('users/{user}/role', [UsersController::class, 'updateRole'])->name('users.updateRole');

        // --------------------- Roles & Permissions ------------------//
        Route::get('roles', [RolesController::class, 'index'])->name('roles.index');
        Route::post('roles', [RolesController::class, 'store'])->name('roles.store');
        Route::delete('roles/{role}', [RolesController::class, 'destroy'])->name('roles.destroy');
        Route::get('permissions', [PermissionsController::class, 'index'])->name('permissions.index');
        Route::put('permissions', [PermissionsController::class, 'update'])->name('permissions.update');
        Route::post('permissions', [PermissionsController::class, 'store'])->name('permissions.store');
        Route::delete('permissions', [PermissionsController::class, 'destroy'])->name('permissions.destroy');

        // --------------------- Clients (Create) ------------------//
        Route::get('clients', [ClientsController::class, 'create'])->name('clients');
        Route::post('clients', [ClientsController::class, 'store'])->name('clients.store');

        // --------------------- Client View/Delete/Archive ------------------//
        Route::get('clients/{client}', [ClientsController::class, 'show'])->name('clients.show');
        Route::delete('clients/{client}', [ClientsController::class, 'destroy'])->name('clients.destroy');
        Route::post('clients/{client}/archive', [ClientsController::class, 'archive'])->name('clients.archive');

        // --------------------- Client Edit ------------------//
        Route::get('clients/{client}/edit', [ClientEditController::class, 'edit'])->name('clients.edit');
        Route::put('clients/{client}', [ClientEditController::class, 'update'])->name('clients.update');

        // --------------------- Client List ------------------//
        Route::get('client-list', [ClientListController::class, 'index'])->name('client.list');

        // --------------------- Duplicate Review ------------------//
        Route::get('duplicate-review', [DuplicateReviewController::class, 'index'])->name('duplicate.review');

        // --------------------- Fingerprint ------------------//
        Route::post('client-list/fingerprint-search', [FingerprintController::class, 'search'])->name('client.search.fingerprint');
        Route::get('fingerprint/health', [FingerprintController::class, 'health'])->name('fingerprint.health');
        Route::post('fingerprint/capture', [FingerprintController::class, 'capture'])->name('fingerprint.capture');
        Route::post('fingerprint/start-bridge', [FingerprintController::class, 'startBridge'])->name('fingerprint.start-bridge');

        // --------------------- Archive ------------------//
        Route::get('archive', [ArchiveController::class, 'index'])->name('archive.list');
        Route::post('archive/{archivedClient}/restore', [ArchiveController::class, 'restore'])->name('archive.restore');

        // --------------------- PSGC Data ------------------//
        Route::get('psgc/provinces', [ClientsController::class, 'psgcProvinces'])->name('psgc.provinces');
        Route::get('psgc/provinces/{provinceCode}/cities', [ClientsController::class, 'psgcCities'])->name('psgc.cities');
        Route::get('psgc/cities/{cityCode}/barangays', [ClientsController::class, 'psgcBarangays'])->name('psgc.barangays');

        // --------------------- Camera ------------------//
        Route::post('camera/upload', [CameraController::class, 'upload'])->name('camera.upload');

        // --------------------- Profile ------------------//
        Route::get('profile', [ProfileController::class, 'profile'])->name('profile');

        // --------------------- Settings ------------------//
        Route::get('settings', [SettingsController::class, 'index'])->name('settings');
        Route::put('profile/update', [SettingsController::class, 'update'])->name('profile.update');

        // --------------------- Transactions ------------------//
        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('transactions/category/{category}', [TransactionController::class, 'categoryList'])->name('transactions.category');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{id}/process', [TransactionController::class, 'process'])->name('transactions.process');
        Route::get('transactions/{id}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::get('transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::put('transactions/{id}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::post('transactions/{id}/subject', [TransactionController::class, 'storeSubject'])->name('transactions.subject.store');

        // --------------------- Transaction Requirements ------------------//
        Route::post('transaction-requirements', [TransactionRequirementController::class, 'store'])->name('transaction-requirements.store');
        Route::get('transaction-requirements/{transactionId}', [TransactionRequirementController::class, 'show'])->name('transaction-requirements.show');
        Route::delete('transaction-requirements/{requirementId}', [TransactionRequirementController::class, 'destroy'])->name('transaction-requirements.destroy');
        Route::get('transaction-requirements/{requirementId}/download', [TransactionRequirementController::class, 'download'])->name('transaction-requirements.download');

        // --------------------- Transaction Events ------------------//
        Route::get('transaction-events', [TransactionEventsController::class, 'index'])->name('transaction-events.index');
        Route::get('transaction-events/duplicate-review', [TransactionEventsController::class, 'duplicateReview'])->name('transaction-events.duplicate-review');
        Route::post('transaction-events/{event}/not-duplicate', [TransactionEventsController::class, 'markNotDuplicate'])->name('transaction-events.not-duplicate');
        Route::post('transaction-events/{event}/reset-duplicate', [TransactionEventsController::class, 'resetNotDuplicate'])->name('transaction-events.reset-duplicate');
        Route::get('transaction-events/archives', [TransactionEventsController::class, 'archives'])->name('transaction-events.archives');
        Route::get('transaction-events/archives/{filename}', [TransactionEventsController::class, 'downloadArchive'])->name('transaction-events.archives.download');
        Route::post('transaction-events/preview', [TransactionEventsController::class, 'preview'])->name('transaction-events.preview');
        Route::post('transaction-events/import', [TransactionEventsController::class, 'import'])->name('transaction-events.import');
        Route::get('transaction-events/template', [TransactionEventsController::class, 'downloadTemplate'])->name('transaction-events.template');
        Route::post('transaction-events/{event}/transfer', [TransactionEventsController::class, 'transfer'])->name('transaction-events.transfer');
        Route::post('transaction-events/transfer-selected', [TransactionEventsController::class, 'transferSelected'])->name('transaction-events.transfer-selected');
    
        });
    });
});
