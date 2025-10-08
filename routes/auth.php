<?php

use App\Http\Controllers\Auth\{
    AuthenticatedSessionController,
    ConfirmablePasswordController,
    EmailVerificationNotificationController,
    EmailVerificationPromptController,
    NewPasswordController,
    PasswordController,
    PasswordResetLinkController,
    RegisteredUserController,
    VerifyEmailController
};
use Illuminate\Support\Facades\Route;

// ======================
// Guest routes
// ======================
Route::middleware('guest')->group(function () {
    Route::controller(RegisteredUserController::class)->group(function () {
        Route::get('register', 'create')->name('register');
        Route::post('register', 'store');
    });

    Route::controller(AuthenticatedSessionController::class)->group(function () {
        Route::get('login', 'create')->name('login');
        Route::post('login', 'store');
    });

    // Uncomment kalau mau aktifkan fitur forgot password
    /*
    Route::controller(PasswordResetLinkController::class)->group(function () {
        Route::get('forgot-password', 'create')->name('password.request');
        Route::post('forgot-password', 'store')->name('password.email');
    });

    Route::controller(NewPasswordController::class)->group(function () {
        Route::get('reset-password/{token}', 'create')->name('password.reset');
        Route::post('reset-password', 'store')->name('password.store');
    });
    */
});

// ======================
// Authenticated routes
// ======================
Route::middleware('auth')->group(function () {
    // Email verification routes
    Route::controller(EmailVerificationPromptController::class)->group(function () {
        Route::get('verify-email', 'show')->name('verification.notice');
    });

    Route::controller(VerifyEmailController::class)->group(function () {
        Route::get('verify-email/{id}/{hash}', '__invoke')
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
    });

    // Password management
    Route::controller(PasswordController::class)->group(function () {
        Route::put('password', 'update')->name('password.update');
    });

    Route::controller(ConfirmablePasswordController::class)->group(function () {
        Route::get('confirm-password', 'show')->name('password.confirm');
        Route::post('confirm-password', 'store');
    });

    // Logout
    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
