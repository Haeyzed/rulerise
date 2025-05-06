<?php

use App\Http\Controllers\Admin\BlogPostCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\FaqController;
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
//Route::post('auth/login', [AuthController::class, 'login']);

// Routes that require authentication
Route::middleware(['auth:api', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('dashboard-overview', [DashboardController::class, 'index']);

    // Change password
//    Route::post('change-password', [AuthController::class, 'changePassword']);

    // Candidates
    Route::prefix('candidate')->group(function () {
        // List all candidates
        Route::get('/', [CandidatesController::class, 'index']);

        // Candidate profile tabs
        Route::get('{id}/profile', [CandidatesController::class, 'getProfileDetails']);
        Route::get('{id}/applications', [CandidatesController::class, 'getApplications']);

        // Legacy route - keep for backward compatibility
        Route::get('{id}', [CandidatesController::class, 'show']);

        // Candidate actions
        Route::post('{id}/delete', [CandidatesController::class, 'delete']);
        Route::post('{id}/moderate-account-status', [CandidatesController::class, 'moderateAccountStatus']);
        Route::post('{id}/set-shadow-ban', [CandidatesController::class, 'setShadowBan']);
    });

    // Employers
    Route::prefix('employer')->group(function () {
//        Route::get('/', [EmployersController::class, 'index']);
//        Route::get('{id}', [EmployersController::class, 'show']);
        Route::post('{id}/delete', [EmployersController::class, 'delete']);
        Route::post('{id}/moderate-account-status', [EmployersController::class, 'moderateAccountStatus']);
        Route::post('{id}/set-shadow-ban', [EmployersController::class, 'setShadowBan']);

        //New api
        Route::get('/', [EmployersController::class, 'getEmployers']);
        Route::get('{id}/profile', [EmployersController::class, 'getProfileDetails']);
        Route::get('{id}/jobs', [EmployersController::class, 'getJobListings']);
        Route::get('{id}/hired-candidates', [EmployersController::class, 'getHiredCandidates']);
        Route::get('{id}/transactions', [EmployersController::class, 'getTransactions']);
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

    // Blog Posts
    Route::apiResource('blog-posts', BlogPostController::class);
    Route::prefix('blog-posts')->name('blog-posts.')->group(function () {
        // Soft delete management
        Route::delete('/{blogPost}/force', [BlogPostController::class, 'forceDestroy'])
            ->name('force-destroy')
            ->withTrashed();
        Route::post('/{blogPost}/restore', [BlogPostController::class, 'restore'])
            ->name('restore')
            ->withTrashed();
    });

    // Blog Post Categories
    Route::apiResource('blog-post-categories', BlogPostCategoryController::class);
    Route::prefix('blog-post-categories')->name('blog-post-categories.')->group(function () {
        // Soft delete management
        Route::delete('/{blogPostCategory}/force', [BlogPostCategoryController::class, 'forceDestroy'])
            ->name('force-destroy')
            ->withTrashed();
        Route::post('/{blogPostCategory}/restore', [BlogPostCategoryController::class, 'restore'])
            ->name('restore')
            ->withTrashed();
        // Reordering
        Route::post('/reorder', [BlogPostCategoryController::class, 'reorder'])
            ->name('reorder');
    });

    Route::prefix('admin/faqs')->group(function () {
        // FAQ Categories
        Route::get('/categories', [FaqController::class, 'getAllCategories']);
        Route::get('/categories/{id}', [FaqController::class, 'getCategory']);
        Route::post('/categories', [FaqController::class, 'createCategory']);
        Route::put('/categories/{id}', [FaqController::class, 'updateCategory']);
        Route::delete('/categories/{id}', [FaqController::class, 'deleteCategory']);
        Route::post('/categories/reorder', [FaqController::class, 'reorderCategories']);

        // FAQs
        Route::get('/', [FaqController::class, 'getAllFaqs']);
        Route::get('/{id}', [FaqController::class, 'getFaq']);
        Route::post('/', [FaqController::class, 'createFaq']);
        Route::put('/{id}', [FaqController::class, 'updateFaq']);
        Route::delete('/{id}', [FaqController::class, 'deleteFaq']);
        Route::post('/reorder', [FaqController::class, 'reorderFaqs']);
    });
});
