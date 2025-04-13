<?php

use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth routes
    // Public routes
Route::post('register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login'])->name('login');

// Password reset
Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('verify-reset-token', [AuthController::class, 'verifyResetToken']);
Route::post('reset-password', [AuthController::class, 'resetPassword']);

// Email verification
//Route::post('email/resend', [AuthController::class, 'resendVerificationEmail']);
//Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
//    ->name('verification.verify');

Route::post('email/resend', [AuthController::class, 'resendVerificationEmail']);
Route::get('email/verify', [AuthController::class, 'verifyEmail'])
    ->name('verification.verify');

// Protected routes
Route::middleware('auth:api')->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::delete('delete-account', [AuthController::class, 'deleteAccount'])
        ->name('auth.delete-account');
});
