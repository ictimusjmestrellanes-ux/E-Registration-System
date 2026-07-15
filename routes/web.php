<?php

use App\Http\Controllers\ActivityLogsController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LockScreenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\CameraController;
use App\Http\Controllers\ClientEditController;
use App\Http\Controllers\ClientListController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\FingerprintController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TransactionEventsController;
use App\Http\Controllers\TransactionRequirementController;
use App\Http\Controllers\UsersController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Auth::routes();

Route::group(['namespace' => 'App\Http\Controllers\Auth'],function()
{
    // ----------------------------- login ------------------------------------//
    Route::get('/login', [LoginController::class, 'login'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate']);
    Route::get('auth/google/redirect', [LoginController::class, 'redirectToGoogle'])->name('google.redirect');
    Route::get('auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('google.callback');
    Route::get('auth/azure/redirect', [LoginController::class, 'redirectToAzure'])->name('azure.redirect');
    Route::get('auth/azure/callback', [LoginController::class, 'handleAzureCallback'])->name('azure.callback');

    // ----------------------------- register -------------------------------//
    Route::get('/register', [RegisterController::class, 'register'])->name('register');
    Route::post('/register', [RegisterController::class, 'storeUser'])->name('register');

    // ----------------------------- Forget Password --------------------------//
    Route::get('forget-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('forget-password');    
    Route::post('forget-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('forget-password');

    // Lock the screen
    Route::get('/lock', function () {
        session(['locked' => true]);
        return redirect()->route('lockscreen')->with('success', 'Locked successfully!');
    })->name('lock-activate');

    Route::controller(LockScreenController::class)->group(function () {
        // ---------------------------- Lock Screen ---------------------------//
        Route::get('lockscreen', [LockScreenController::class, 'lockscreen'])->name('lockscreen');
        Route::post('unlock', [LockScreenController::class, 'unlock'])->name('unlock-screen');
    });

});

Route::group(['namespace' => 'App\Http\Controllers'], function () {
    Route::middleware('auth')->group(function () {
        // --------------------- Dashboard ------------------//
        Route::get('dashboard', [ProfileController::class, 'dashboard'])->name('dashboard');

        // --------------------- Activity Logs ------------------//
        Route::get('activity-logs', [ActivityLogsController::class, 'index'])->name('activity.logs');

        // --------------------- Users ------------------//
        Route::get('users', [UsersController::class, 'index'])->name('users.index');
        Route::put('users/{user}/role', [UsersController::class, 'updateRole'])->name('users.updateRole');

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
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{id}', [TransactionController::class, 'show'])->name('transactions.show');
        Route::post('transactions/{id}/subject', [TransactionController::class, 'storeSubject'])->name('transactions.subject.store');

        // --------------------- Transaction Requirements ------------------//
        Route::post('transaction-requirements', [TransactionRequirementController::class, 'store'])->name('transaction-requirements.store');
        Route::get('transaction-requirements/{transactionId}', [TransactionRequirementController::class, 'show'])->name('transaction-requirements.show');
        Route::delete('transaction-requirements/{requirementId}', [TransactionRequirementController::class, 'destroy'])->name('transaction-requirements.destroy');
        Route::get('transaction-requirements/{requirementId}/download', [TransactionRequirementController::class, 'download'])->name('transaction-requirements.download');

        // --------------------- Transaction Events ------------------//
        Route::get('transaction-events', [TransactionEventsController::class, 'index'])->name('transaction-events.index');
        Route::post('transaction-events/import', [TransactionEventsController::class, 'import'])->name('transaction-events.import');
        Route::delete('transaction-events/{transactionEvent}', [TransactionEventsController::class, 'destroy'])->name('transaction-events.destroy');
    
    });
});
