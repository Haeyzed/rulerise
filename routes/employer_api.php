<?php

use App\Http\Controllers\Employer\CandidatesController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Employer\DashboardsController;
use App\Http\Controllers\Employer\MetaInformationController;
use App\Http\Controllers\Employer\JobsController;
use App\Http\Controllers\Employer\JobApplicantController;
use App\Http\Controllers\Employer\CandidateJobPoolsController;
use App\Http\Controllers\Employer\EmployersController;
use App\Http\Controllers\Employer\MessagesController;
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

// Routes that require authentication
Route::middleware(['auth:api', 'role:employer,employer_staff'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardsController::class, 'index']);

    // Meta information
    Route::get('jobCategory', [MetaInformationController::class, 'getJobCategory']);

    // Jobs
    Route::prefix('job')->group(function () {
        Route::get('/', [JobsController::class, 'index']);
        Route::post('/', [JobsController::class, 'store']);
        Route::get('{id}', [JobsController::class, 'show']);
        Route::put('{id}', [JobsController::class, 'update']);
        Route::delete('{id}', [JobsController::class, 'delete']);
        Route::get('{id}/filter-applicants-by-job', [JobApplicantController::class, 'filterApplicantsByJob']);
        Route::post('applicants/update-hiring-stage', [JobApplicantController::class, 'changeHiringStage']);
        Route::post('applicants/batch-update-hiring-stage', [JobApplicantController::class, 'batchChangeHiringStage']);
        Route::post('{id}/set-open-close', [JobsController::class, 'setOpenClose']);
        Route::post('{id}/publish-job', [JobsController::class, 'publishJob']);
        Route::get('{id}/view-application', [JobApplicantController::class, 'viewApplication']);
    });

    // Candidate pools
    Route::prefix('candidate-pool')->group(function () {
        Route::get('/', [CandidateJobPoolsController::class, 'index']);
        Route::post('/', [CandidateJobPoolsController::class, 'store']);
        Route::get('{id}/view-candidate', [CandidateJobPoolsController::class, 'viewCandidate']);
        Route::post('attach-candidate', [CandidateJobPoolsController::class, 'attachCandidatePool']);
        Route::post('detach-candidate', [CandidateJobPoolsController::class, 'detachCandidatePool']);
        Route::post('attach-multiple-candidate', [CandidateJobPoolsController::class, 'attachCandidatesPool']);
        Route::post('detach-multiple-candidate', [CandidateJobPoolsController::class, 'detachCandidatesPool']);
    });

    // Candidates
    Route::prefix('candidate')->group(function () {
        Route::get('/', [CandidatesController::class, 'index']);
        Route::get('{id}', [CandidatesController::class, 'show']);
    });

    // Messages
    Route::prefix('messages')->group(function () {
        Route::get('/', [MessagesController::class, 'index']);
        Route::post('/', [MessagesController::class, 'store']);
        Route::get('{id}', [MessagesController::class, 'show']);
        Route::delete('{id}', [MessagesController::class, 'destroy']);
        Route::post('{id}/mark-as-read', [MessagesController::class, 'markAsRead']);
        Route::post('mark-all-as-read', [MessagesController::class, 'markAllAsRead']);
        Route::get('unread-count', [MessagesController::class, 'getUnreadCount']);
    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [EmployersController::class, 'getProfile']);
        Route::put('/', [EmployersController::class, 'updateProfile']);
        Route::put('upload-logo', [EmployersController::class, 'uploadLogo']);
        Route::post('delete-account', [EmployersController::class, 'deleteAccount']);
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
});

// Routes that require employer role (not staff)
Route::middleware(['auth:api', 'role:employer'])->group(function () {
    // Staff management
    Route::prefix('staff')->group(function () {
        Route::get('/', [UsersController::class, 'index']);
        Route::get('{id}', [UsersController::class, 'show']);
        Route::post('/', [UsersController::class, 'store']);
        Route::put('/', [UsersController::class, 'update']);
        Route::delete('{id}', [UsersController::class, 'delete']);
    });

    // Subscription management
    Route::prefix('subscriptions')->group(function () {
        // Get subscription plans
        Route::get('/plans', [SubscriptionPaymentController::class, 'subscriptionList']);

        // Create payment link
        Route::get('/payment-link/{id}', [SubscriptionPaymentController::class, 'createPaymentLink']);

        // Verify subscription payment
        Route::post('/verify', [SubscriptionPaymentController::class, 'verifySubscription'])
            ->name('employer.subscription.verify');

        // Update subscription
        Route::put('/{id}', [SubscriptionPaymentController::class, 'updateSubscription']);

        // Cancel subscription
        Route::delete('/{id}', [SubscriptionsController::class, 'cancelSubscription']);

        // Get subscription information
        Route::get('/info', [SubscriptionsController::class, 'subscriptionInformation']);

        // List all subscriptions
        Route::get('/', [SubscriptionsController::class, 'listSubscriptions']);

        // Update CV download usage
        Route::post('/cv-download', [SubscriptionsController::class, 'updateCVDownloadUsage']);
    });
});
