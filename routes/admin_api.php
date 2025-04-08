<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\CandidatesController;
use App\Http\Controllers\Admin\EmployersController;
use App\Http\Controllers\Admin\SubscriptionPlansController;
use App\Http\Controllers\Admin\JobCategoriesController;
use App\Http\Controllers\Admin\WebsiteCustomizationsController;
use App\Http\Controllers\Admin\GeneralSettingsController;
use App\Http\Controllers\Admin\RolesController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\Admin\PermissionController;

/*
|--------------------------------------------------------------------------
| Admin API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for admin users. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Auth routes (no auth required)
Route::post('auth/login', [AuthController::class, 'login']);

// Routes that require authentication
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('dashboard-overview', [DashboardController::class, 'index']);

    // Change password
    Route::post('change-password', [AuthController::class, 'changePassword']);

    // Candidates
    Route::prefix('candidate')->group(function () {
        Route::get('/', [CandidatesController::class, 'index']);
        Route::get('{id}', [CandidatesController::class, 'show']);
        Route::post('{id}/delete', [CandidatesController::class, 'delete']);
        Route::post('{id}/moderateAccountStatus', [CandidatesController::class, 'moderateAccountStatus']);
        Route::post('{id}/setShadowBan', [CandidatesController::class, 'setShadowBan']);
    });

    // Employers
    Route::prefix('employer')->group(function () {
        Route::get('/', [EmployersController::class, 'index']);
        Route::get('{id}', [EmployersController::class, 'show']);
        Route::post('{id}/delete', [EmployersController::class, 'delete']);
        Route::post('{id}/moderateAccountStatus', [EmployersController::class, 'moderateAccountStatus']);
        Route::post('{id}/setShadowBan', [EmployersController::class, 'setShadowBan']);
    });

    // Subscription plans
    Route::prefix('plan')->group(function () {
        Route::get('/', [SubscriptionPlansController::class, 'index']);
        Route::post('/', [SubscriptionPlansController::class, 'store']);
        Route::get('{id}', [SubscriptionPlansController::class, 'show']);
        Route::post('update', [SubscriptionPlansController::class, 'update']);
        Route::post('{id}/delete', [SubscriptionPlansController::class, 'destroy']);
        Route::post('setActive', [SubscriptionPlansController::class, 'setActive']);
    });

    // Job categories
    Route::prefix('job-category')->group(function () {
        Route::get('/', [JobCategoriesController::class, 'index']);
        Route::post('/', [JobCategoriesController::class, 'store']);
        Route::get('{id}', [JobCategoriesController::class, 'show']);
        Route::post('update', [JobCategoriesController::class, 'update']);
        Route::post('{id}/delete', [JobCategoriesController::class, 'delete']);
        Route::post('{id}/setActive', [JobCategoriesController::class, 'setActive']);
    });

    // Website customizations
    Route::prefix('website-customization')->group(function () {
        Route::post('/', [WebsiteCustomizationsController::class, 'store']);
        Route::get('{type}', [WebsiteCustomizationsController::class, 'index']);
        Route::post('createNewContact', [WebsiteCustomizationsController::class, 'addNewContact']);
        Route::post('uploadImage', [WebsiteCustomizationsController::class, 'uploadImage']);
    });

    // General settings
    Route::prefix('general-setting')->group(function () {
        Route::get('/', [GeneralSettingsController::class, 'index']);
        Route::post('/', [GeneralSettingsController::class, 'store']);
    });

    // User management - Roles
    Route::prefix('user-management/role')->group(function () {
        Route::get('/', [RolesController::class, 'index']);
        Route::get('{roleName}', [RolesController::class, 'show']);
        Route::post('/', [RolesController::class, 'store']);
        Route::post('update', [RolesController::class, 'update']);
        Route::post('{roleName}/delete', [RolesController::class, 'delete']);
    });

    // User management - Users
    Route::prefix('user-management/user')->group(function () {
        Route::get('/', [UsersController::class, 'index']);
        Route::get('{id}', [UsersController::class, 'show']);
        Route::post('/', [UsersController::class, 'store']);
        Route::post('update', [UsersController::class, 'update']);
        Route::post('{id}/delete', [UsersController::class, 'delete']);
    });

    // User management - Permissions
    Route::get('user-management/permissions', [PermissionController::class, 'index']);
});
