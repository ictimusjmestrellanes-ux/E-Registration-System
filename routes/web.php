<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\GoogleController;
use App\Http\Controllers\Auth\LockScreenController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Auth::routes();

Route::group(['namespace' => 'App\Http\Controllers\Auth'],function()
{
    // ----------------------------- login ------------------------------------//
    Route::controller(LoginController::class)->group(function () {
        Route::get('/login', 'login')->name('login');
        Route::post('/login', 'authenticate');
        Route::get('/logout', 'logout')->name('logout');
        Route::get('logout/page', 'logoutPage')->name('logout/page');
    });

    Route::controller(GoogleController::class)->group(function () {
        Route::get('/auth/google', 'redirect')->name('google.login');
        Route::get('/auth/google/callback', 'callback')->name('google.callback');
    });

    // ----------------------------- register -------------------------------//
    Route::controller(RegisterController::class)->group(function () {
        Route::get('/register', 'register')->name('register');
        Route::post('/register','storeUser')->name('register');    
    });

    // ----------------------------- Forget Password --------------------------//
    Route::controller(ForgotPasswordController::class)->group(function () {
        Route::get('forget-password', 'showLinkRequestForm')->name('forget-password');    
        Route::post('forget-password', 'sendResetLinkEmail')->name('forget-password');    
    });

    // ---------------------------- Reset Password ----------------------------//
    Route::controller(ResetPasswordController::class)->group(function () {
        Route::get('reset-password/{token}', 'getPassword');
        Route::post('reset-password', 'updatePassword')->name('reset-password');    
    });

    // Lock the screen
    Route::get('/lock', function () {
        session(['locked' => true]);
        return redirect()->route('lockscreen')->with('success', 'Locked successfully!');
    })->name('lock-activate');

    Route::controller(LockScreenController::class)->group(function () {
        // ---------------------------- Lock Screen ---------------------------//
        Route::get('lockscreen', 'lockscreen')->name('lockscreen');
        Route::post('unlock',  'unlock')->name('unlock-screen');
    });

});

Route::group(['namespace' => 'App\Http\Controllers'], function () {
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [ProfileController::class, 'dashboard'])->name('dashboard');
        Route::get('activity-logs', [ProfileController::class, 'activityLogs'])->name('activity.logs');
        Route::get('clients', [ProfileController::class, 'clients'])->name('clients');
        Route::get('client-list', [ProfileController::class, 'clientList'])->name('client.list');
        Route::post('client-list/fingerprint-search', [ProfileController::class, 'searchClientByFingerprint'])->name('client.search.fingerprint');
        Route::get('archive', [ProfileController::class, 'archiveList'])->name('archive.list');
        Route::post('archive/{archivedClient}/restore', [ProfileController::class, 'restoreArchivedClient'])->name('archive.restore');
        Route::get('psgc/provinces', [ProfileController::class, 'psgcProvinces'])->name('psgc.provinces');
        Route::get('psgc/provinces/{provinceCode}/cities', [ProfileController::class, 'psgcCities'])->name('psgc.cities');
        Route::get('psgc/cities/{cityCode}/barangays', [ProfileController::class, 'psgcBarangays'])->name('psgc.barangays');
        Route::get('clients/{client}', [ProfileController::class, 'viewClient'])->name('clients.show');
        Route::get('clients/{client}/edit', [ProfileController::class, 'editClient'])->name('clients.edit');
        Route::put('clients/{client}', [ProfileController::class, 'updateClient'])->name('clients.update');
        Route::post('clients/{client}/archive', [ProfileController::class, 'archiveClient'])->name('clients.archive');
        Route::delete('clients/{client}', [ProfileController::class, 'destroyClient'])->name('clients.destroy');
        Route::post('clients', [ProfileController::class, 'storeClient'])->name('clients.store');

        // --------------------- Profile ------------------//
        Route::controller(ProfileController::class)->group(function () {
            Route::get('profile', 'profile')->name('profile');
            Route::get('settings', 'profileSettings')->name('settings');
            Route::put('profile/update', 'updateProfile')->name('profile.update');
            Route::get('faqs', 'faqs')->name('faqs');
        });
    });
});
