<?php

use App\Http\Controllers\Employer\CandidatesController;
use App\Http\Controllers\Employer\PaymentController;
use App\Http\Controllers\Employer\ResumeController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Employer\DashboardsController;
use App\Http\Controllers\Employer\JobsController;
use App\Http\Controllers\Employer\JobApplicantController;
use App\Http\Controllers\Employer\CandidateJobPoolsController;
use App\Http\Controllers\Employer\EmployersController;
use App\Http\Controllers\Employer\MessagesController;
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

    // Jobs
    Route::prefix('job')->group(function () {
        Route::get('/', [JobsController::class, 'index']);
        Route::post('/', [JobsController::class, 'store']);
        Route::get('{id}', [JobsController::class, 'show']);
        Route::put('{id}', [JobsController::class, 'update']);
        Route::delete('{id}', [JobsController::class, 'destroy']);
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

        // New multi-pool endpoints
        Route::post('attach-candidates-multi-pool', [CandidateJobPoolsController::class, 'attachCandidatesMultiPool']);
        Route::post('detach-candidates-multi-pool', [CandidateJobPoolsController::class, 'detachCandidatesMultiPool']);
    });

    // CV Search
    Route::prefix('cv-search')->group(function () {
        Route::get('/', [ResumeController::class, 'searchCandidates']);
        Route::get('/recommended', [ResumeController::class, 'recommendedCandidates']);
        Route::get('/degrees', [ResumeController::class, 'getDegrees']);
        Route::get('/candidate/{id}', [ResumeController::class, 'showCandidate']);
        Route::get('/download/{id}', [ResumeController::class, 'downloadResume']);
        Route::get('/subscription-usage', [ResumeController::class, 'getSubscriptionUsage']);
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
        Route::post('unread-count', [MessagesController::class, 'getUnreadCount']);
    });

    // Profile
    Route::prefix('profile')->group(function () {
        Route::get('/', [EmployersController::class, 'getProfile']);
        Route::post('/', [EmployersController::class, 'updateProfile']);
        Route::post('upload-logo', [EmployersController::class, 'uploadLogo']);
        Route::post('delete-account', [EmployersController::class, 'deleteAccount']);
    });

    // Plans
    Route::get('/plans', [PaymentController::class, 'getPlans']);

    // Payments
    Route::post('/payments/one-time', [PaymentController::class, 'createOneTimePayment']);
    Route::post('/payments/paypal/capture', [PaymentController::class, 'capturePayPalPayment']);
    Route::post('payments/stripe/capture', [PaymentController::class, 'completeStripeCheckout'])
        ->name('complete-checkout');

    // Subscriptions
    Route::post('/subscriptions', [PaymentController::class, 'createSubscription']);
    Route::get('/subscriptions/active', [PaymentController::class, 'getActiveSubscription']);
    Route::delete('/subscriptions/cancel', [PaymentController::class, 'cancelSubscription']);
    Route::post('/subscriptions/suspend', [PaymentController::class, 'suspendSubscription']);
    Route::post('/subscriptions/resume', [PaymentController::class, 'resumeSubscription']);
    Route::get('/subscriptions', [PaymentController::class, 'getSubscriptions']);

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
        Route::match(['put','patch'],'/{id}', [UsersController::class, 'update']);
        Route::delete('{id}', [UsersController::class, 'delete']);
    });
});

// Public routes
Route::post('/webhooks/stripe', [WebhookController::class, 'handleStripeWebhook']);
Route::post('/webhooks/paypal', [WebhookController::class, 'handlePayPalWebhook']);



// ========================================
// WEBHOOK ROUTES (No Authentication)
// ========================================

//Route::prefix('webhooks')->name('webhooks.')->group(function () {
//    // PayPal webhook endpoint
//    Route::post('paypal', [WebhookController::class, 'handlePayPalWebhook'])
//        ->name('paypal');
//
//    // Stripe webhook endpoint
//    Route::post('stripe', [WebhookController::class, 'handleStripeWebhook'])
//        ->name('stripe');
//
//    // Health check for webhook monitoring
//    Route::get('health', [WebhookController::class, 'healthCheck'])
//        ->name('health');
//});

// ========================================
// EMPLOYER ROUTES (Authenticated)
// ========================================

//Route::middleware(['auth:sanctum', 'verified'])->prefix('employer')->name('employer.')->group(function () {
//
//    // Plan Management
//    Route::prefix('plans')->name('plans.')->group(function () {
//        Route::get('/', [PaymentController::class, 'getPlans'])
//            ->name('index');
//        Route::get('/{plan}', [PaymentController::class, 'getPlan'])
//            ->name('show');
//    });
//
//    // Subscription Management
//    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
//        // Get subscriptions
//        Route::get('/', [PaymentController::class, 'getSubscriptions'])
//            ->name('index');
//
//        // Get active subscription
//        Route::get('active', [PaymentController::class, 'getActiveSubscription'])
//            ->name('active');
//
//        // Create subscription
//        Route::post('/', [PaymentController::class, 'createSubscription'])
//            ->name('create');
//
//        // Subscription actions
//        Route::patch('{subscription}/cancel', [PaymentController::class, 'cancelSubscription'])
//            ->name('cancel');
//        Route::patch('{subscription}/suspend', [PaymentController::class, 'suspendSubscription'])
//            ->name('suspend');
//        Route::patch('{subscription}/resume', [PaymentController::class, 'resumeSubscription'])
//            ->name('resume');
//    });
//
//    // Payment Management
//    Route::prefix('payments')->name('payments.')->group(function () {
//        // Create one-time payment
//        Route::post('one-time', [PaymentController::class, 'createOneTimePayment'])
//            ->name('one-time');
//
//        // Payment provider specific endpoints
//        Route::prefix('stripe')->name('stripe.')->group(function () {
//            Route::post('checkout-session/{sessionId}/complete', [PaymentController::class, 'completeStripeCheckout'])
//                ->name('complete-checkout');
//        });
//
//        Route::prefix('paypal')->name('paypal.')->group(function () {
//            Route::post('capture/{orderId}', [PaymentController::class, 'capturePayPalPayment'])
//                ->name('capture');
//        });
//    });
//
//    // Billing & Usage
//    Route::prefix('billing')->name('billing.')->group(function () {
//        Route::get('usage', [PaymentController::class, 'getUsageStats'])
//            ->name('usage');
//        Route::get('invoices', [PaymentController::class, 'getInvoices'])
//            ->name('invoices');
//        Route::get('payment-methods', [PaymentController::class, 'getPaymentMethods'])
//            ->name('payment-methods');
//    });
//});
//
//// ========================================
//// ADMIN ROUTES (Admin Authentication)
//// ========================================
//
//Route::middleware(['auth:sanctum', 'admin'])->prefix('admin')->name('admin.')->group(function () {
//
//    // Subscription Management
//    Route::prefix('subscriptions')->name('subscriptions.')->group(function () {
//        Route::get('/', [PaymentController::class, 'adminGetSubscriptions'])
//            ->name('index');
//        Route::get('stats', [PaymentController::class, 'getSubscriptionStats'])
//            ->name('stats');
//        Route::patch('{subscription}/force-cancel', [PaymentController::class, 'forceCancel'])
//            ->name('force-cancel');
//    });
//
//    // Plan Management
//    Route::prefix('plans')->name('plans.')->group(function () {
//        Route::get('analytics', [PaymentController::class, 'getPlanAnalytics'])
//            ->name('analytics');
//    });
//});
