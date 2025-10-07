<?php

use Adamus\LaravelIamClient\Http\Controllers\IAMAuthController;
use Illuminate\Support\Facades\Route;

// Wrap all auth routes in web middleware to ensure session support
Route::middleware('web')->group(function () {
    // Login page route
    Route::get('login', function () {
        return inertia('auth/login', [
            'canResetPassword' => false,
        ]);
    })->middleware('guest')->name('login');

    // IAM Authentication Routes
    Route::middleware('guest')->group(function () {
        Route::post('login', [IAMAuthController::class, 'login'])->name('iam.login');
        
        // Phone/OTP authentication routes
        Route::post('auth/send-otp', [IAMAuthController::class, 'sendOtp'])->name('iam.send-otp');
        Route::post('auth/login-with-phone', [IAMAuthController::class, 'loginWithPhone'])->name('iam.login-with-phone');
    });

    Route::middleware('iam.auth')->group(function () {
        Route::get('auth/me', [IAMAuthController::class, 'me'])->name('iam.me');
        Route::post('auth/check-permission', [IAMAuthController::class, 'checkPermission'])->name('iam.check-permission');
        Route::post('auth/check-role', [IAMAuthController::class, 'checkRole'])->name('iam.check-role');
        Route::post('auth/refresh', [IAMAuthController::class, 'refresh'])->name('iam.refresh');
        Route::post('logout', [IAMAuthController::class, 'logout'])->name('logout');
        Route::post('auth/logout-all', [IAMAuthController::class, 'logoutAll'])->name('iam.logout-all');
        
        // Phone verification routes (authenticated users only)
        Route::post('auth/verify-phone', [IAMAuthController::class, 'verifyPhone'])->name('iam.verify-phone');
        Route::post('auth/confirm-phone-verification', [IAMAuthController::class, 'confirmPhoneVerification'])->name('iam.confirm-phone-verification');
    });
});