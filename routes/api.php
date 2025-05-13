<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Apply throttle:api middleware to all API routes
Route::middleware(['throttle:api'])->group(function () {
    // Include the auth API routes
    Route::prefix('auth')->group(base_path('routes/auth_api.php'));

    // Include the public API routes
    Route::prefix('public')->group(base_path('routes/public_api.php'));

    // Include the candidate API routes
    Route::prefix('candidate')->group(base_path('routes/candidate_api.php'));

    // Include the employer API routes
    Route::prefix('employer')->group(base_path('routes/employer_api.php'));

    // Include the admin API routes
    Route::prefix('admin')->group(base_path('routes/admin_api.php'));
});
