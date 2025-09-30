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
    });

    Route::middleware('iam.auth')->group(function () {
        Route::get('auth/me', [IAMAuthController::class, 'me'])->name('iam.me');
        Route::post('auth/check-permission', [IAMAuthController::class, 'checkPermission'])->name('iam.check-permission');
        Route::post('auth/check-role', [IAMAuthController::class, 'checkRole'])->name('iam.check-role');
        Route::post('auth/refresh', [IAMAuthController::class, 'refresh'])->name('iam.refresh');
        Route::post('logout', [IAMAuthController::class, 'logout'])->name('logout');
        Route::post('auth/logout-all', [IAMAuthController::class, 'logoutAll'])->name('iam.logout-all');
    });
});