<?php

use App\Http\Controllers\Candidate\CandidatesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Employer\DashboardsController;
use App\Http\Controllers\Employer\MetaInformationController;
use App\Http\Controllers\Employer\EmployerJobsController;
use App\Http\Controllers\Employer\JobApplicantController;
use App\Http\Controllers\Employer\CandidateJobPoolsController;
use App\Http\Controllers\Employer\EmployersController;
use App\Http\Controllers\Employer\SubscriptionPaymentController;
use App\Http\Controllers\Employer\SubscriptionsController;
use App\Http\Controllers\Employer\JobNotificationTemplatesController;
use App\Http\Controllers\Employer\UsersController;

/*
|--------------------------------------------------------------------------
| Employer API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for employer users. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Auth routes (no auth required)
Route::group(['prefix' => 'auth'], function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('resendEmailVerification/{id}', [AuthController::class, 'resendEmailVerification']);
    Route::post('verifyEmail', [AuthController::class, 'verifyEmail']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password/{id}', [AuthController::class, 'sendResetPasswordLink']);
    Route::post('verify-forgot-password', [AuthController::class, 'verifyResetPasswordLink']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);
});

// Routes that require authentication
Route::middleware(['auth:api', 'role:employer'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardsController::class, 'index']);

    // Meta information
    Route::get('jobCategory', [MetaInformationController::class, 'getJobCategory']);

    // Jobs
    Route::prefix('job')->group(function () {
        Route::get('/', [EmployerJobsController::class, 'index']);
        Route::post('/', [EmployerJobsController::class, 'store']);
        Route::get('{id}', [EmployerJobsController::class, 'show']);
        Route::post('update', [EmployerJobsController::class, 'update']);
        Route::post('{id}/delete', [EmployerJobsController::class, 'delete']);
        Route::get('{id}/filterApplicantsByJob', [JobApplicantController::class, 'filterApplicantsByJob']);
        Route::post('applicants/update-hiring-stage', [JobApplicantController::class, 'changeHiringStage']);
        Route::post('{id}/setOpenClose', [EmployerJobsController::class, 'setOpenClose']);
        Route::post('{id}/publishJob', [EmployerJobsController::class, 'publishJob']);
        Route::get('{id}/view-application', [JobApplicantController::class, 'viewApplication']);
    });

    // Candidate pools
    Route::prefix('candidate-pool')->group(function () {
        Route::get('/', [CandidateJobPoolsController::class, 'index']);
        Route::post('/', [CandidateJobPoolsController::class, 'store']);
        Route::get('{id}/view-candidate', [CandidateJobPoolsController::class, 'viewCandidate']);
        Route::post('attach-candidate', [CandidateJobPoolsController::class, 'attachCandidatePool']);
    });

    // Candidates
    Route::prefix('candidate')->group(function () {
        Route::get('/', [CandidatesController::class, 'index']);
        Route::get('{id}', [CandidatesController::class, 'show']);
        Route::post('application/change-hiring-stage', [CandidatesController::class, 'changeHiringStage']);
    });

    // Profile
//    Route::prefix('profile')->group(function  'changeHiringStage']);
//    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [EmployersController::class, 'getProfile']);
        Route::post('/', [EmployersController::class, 'updateProfile']);
        Route::post('upload-logo', [EmployersController::class, 'uploadLogo']);
        Route::post('delete-account', [EmployersController::class, 'deleteAccount']);
        Route::post('change-password', [AuthController::class, 'changePassword']);
        Route::post('upload-profile-picture', [UserAccountSettingsController::class, 'uploadProfilePicture']);
    });

    // Subscriptions
    Route::prefix('cv-packages')->group(function () {
        Route::get('/', [SubscriptionPaymentController::class, 'subscriptionList']);
        Route::post('{id}/subscribe', [SubscriptionPaymentController::class, 'createPaymentLink']);
        Route::get('subscription-detail', [SubscriptionsController::class, 'subscriptionInformation']);
        Route::post('update-download-usage', [SubscriptionsController::class, 'updateCVDownloadUsage']);
        Route::post('verifySubscription', [SubscriptionPaymentController::class, 'verifySubscription']);
    });

    // Job notification templates
    Route::prefix('job-notification-template')->group(function () {
        Route::post('/', [JobNotificationTemplatesController::class, 'updateTemplate']);
        Route::get('/', [JobNotificationTemplatesController::class, 'index']);
    });

    // Users
    Route::prefix('users')->group(function () {
        Route::get('/', [UsersController::class, 'index']);
        Route::get('{id}', [UsersController::class, 'show']);
        Route::post('/', [UsersController::class, 'store']);
        Route::post('update', [UsersController::class, 'update']);
        Route::post('{id}/delete', [UsersController::class, 'delete']);
    });
});
