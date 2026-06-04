<?php

use App\Http\Controllers\Auth\ForgotPasswordController;
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

Route::group(['namespace' => 'App\Http\Controllers'],function()
{
    Route::middleware('auth')->group(function () {
        Route::get('dashboard', [ProfileController::class, 'profile'])->name('dashboard');

        // --------------------- Profile ------------------//
        Route::controller(ProfileController::class)->group(function () {
            Route::get('profile', 'profile')->name('profile');
            Route::get('settings', 'profileSettings')->name('settings');
            Route::get('faqs', 'faqs')->name('faqs');
        });
    });
});
