<?php

use App\Http\Controllers\Admin\AccountSettingsController;
use App\Http\Controllers\Admin\BlogPostCategoryController;
use App\Http\Controllers\Admin\BlogPostController;
use App\Http\Controllers\Admin\FaqController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Admin\WebsiteController;
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
use App\Http\Controllers\Admin\PermissionsController;
use App\Http\Controllers\Admin\UserManagementController;

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
    Route::apiResource('plan', SubscriptionPlansController::class);
    Route::prefix('plan')->group(function () {
        Route::post('{id}/set-active', [SubscriptionPlansController::class, 'setActive']);
    });

    // Job categories
    Route::apiResource('job-category', JobCategoriesController::class);
    Route::prefix('job-category')->group(function () {
        Route::post('{id}/set-active', [JobCategoriesController::class, 'setActive']);
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

    // Account settings
    Route::prefix('account-settings')->group(function () {
        Route::get('/', [AccountSettingsController::class, 'index']);
        Route::post('/', [AccountSettingsController::class, 'update']);
        Route::get('/user-configuration', [AccountSettingsController::class, 'getUserConfiguration']);
        Route::post('/user-configuration', [AccountSettingsController::class, 'updateUserConfiguration']);
        Route::get('/currency-configuration', [AccountSettingsController::class, 'getCurrencyConfiguration']);
        Route::post('/currency-configuration', [AccountSettingsController::class, 'updateCurrencyConfiguration']);
    });

    // User management - Roles
    Route::apiResource('roles', RolesController::class)->parameters(['roles' => 'id']);
    Route::prefix('roles')->group(function () {
        Route::get('/all-permissions', [RolesController::class, 'getAllPermissions']);
        Route::post('/assign-to-user', [RolesController::class, 'assignRoleToUser']);
        Route::post('/assign-permissions-to-user', [RolesController::class, 'assignPermissionsToUser']);
    });

    // User management - Permissions
    Route::prefix('permissions')->group(function () {
        Route::get('/', [PermissionsController::class, 'index']);
        Route::post('/', [PermissionsController::class, 'store']);
        Route::get('{id}', [PermissionsController::class, 'show']);
        Route::put('{id}', [PermissionsController::class, 'update']);
        Route::delete('{id}', [PermissionsController::class, 'destroy']);
    });

    // User management - Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UserManagementController::class, 'index']);
        Route::post('/', [UserManagementController::class, 'store']);
        Route::get('{id}', [UserManagementController::class, 'show']);
        Route::put('{id}', [UserManagementController::class, 'update']);
        Route::delete('{id}', [UserManagementController::class, 'destroy']);
        Route::patch('{id}/status', [UserManagementController::class, 'updateStatus']);
        Route::get('/roles', [UserManagementController::class, 'getRoles']);
        Route::get('/permissions', [UserManagementController::class, 'getPermissions']);
    });

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

    // Website Content Management
    Route::prefix('website')->group(function () {
        // Hero Section
        Route::get('/hero-section', [WebsiteController::class, 'getHeroSection']);
        Route::get('/hero-sections', [WebsiteController::class, 'getAllHeroSections']);
        Route::post('/hero-section', [WebsiteController::class, 'createOrUpdateHeroSection']);
        Route::post('/hero-section/{id}', [WebsiteController::class, 'createOrUpdateHeroSection']);
        Route::delete('/hero-section/{id}', [WebsiteController::class, 'deleteHeroSection']);
        Route::delete('/hero-section/image/{imageId}', [WebsiteController::class, 'deleteHeroSectionImage']);

        // About Us
        Route::get('/about-us', [WebsiteController::class, 'getAboutUs']);
        Route::post('/about-us', [WebsiteController::class, 'createOrUpdateAboutUs']);
        Route::post('/about-us/{id}', [WebsiteController::class, 'createOrUpdateAboutUs']);
        Route::delete('/about-us/{id}', [WebsiteController::class, 'deleteAboutUs']);
        Route::delete('/about-us/image/{imageId}', [WebsiteController::class, 'deleteAboutUsImage']);

        // Contact
        Route::get('/contacts', [WebsiteController::class, 'getAllContacts']);
        Route::get('/contact/{id}', [WebsiteController::class, 'getContact']);
        Route::post('/contact', [WebsiteController::class, 'createOrUpdateContact']);
        Route::post('/contact/{id}', [WebsiteController::class, 'createOrUpdateContact']);
        Route::delete('/contact/{id}', [WebsiteController::class, 'deleteContact']);

        // Ad Banner
        Route::get('/ad-banners', [WebsiteController::class, 'getAllAdBanners']);
        Route::get('/ad-banner/{id}', [WebsiteController::class, 'getAdBanner']);
        Route::post('/ad-banner', [WebsiteController::class, 'createOrUpdateAdBanner']);
        Route::post('/ad-banner/{id}', [WebsiteController::class, 'createOrUpdateAdBanner']);
        Route::delete('/ad-banner/{id}', [WebsiteController::class, 'deleteAdBanner']);
        Route::delete('/ad-banner/{adBannerId}/image/{imageId}', [WebsiteController::class, 'deleteAdBannerImage']);
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

    // Upload routes
    Route::post('uploads', [UploadController::class, 'upload'])->name('uploads.upload');
    Route::post('uploads/multiple', [UploadController::class, 'uploadMultiple'])->name('uploads.upload-multiple');
    Route::get('uploads', [UploadController::class, 'index'])->name('uploads.index');
    Route::get('uploads/{upload}', [UploadController::class, 'show'])->name('uploads.show');
    Route::delete('uploads/{upload}', [UploadController::class, 'destroy'])->name('uploads.destroy');
    Route::get('uploads/collection/{collection}', [UploadController::class, 'getByCollection'])->name('uploads.collection');
});
