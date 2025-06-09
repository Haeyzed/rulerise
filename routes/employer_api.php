<?php

use App\Http\Controllers\API\Webhooks\PayPalWebhookController;
use App\Http\Controllers\API\Webhooks\StripeWebhookController;
use App\Http\Controllers\Employer\CandidatesController;
use App\Http\Controllers\Employer\PaymentController;
use App\Http\Controllers\Employer\ResumeController;
use App\Http\Controllers\Employer\SubscriptionController;
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
        Route::post('upload-profile-picture', [UserAccountSettingsController::class, 'uploadProfilePicture']);
    });

    // Subscription Management
    Route::prefix('subscription')->group(function () {
        // Get available plans
        Route::get('plans', [SubscriptionController::class, 'getPlans']);

        // Get active subscription
        Route::get('active', [SubscriptionController::class, 'getActiveSubscription']);

        // Get all subscriptions
        Route::get('all', [SubscriptionController::class, 'getAllSubscriptions']);

        // Subscribe to a plan
        Route::post('{plan}/subscribe', [SubscriptionController::class, 'subscribe']);

        // Check eligibility for a plan
        Route::post('eligibility/{plan}', [SubscriptionController::class, 'checkEligibility']);

        // Activate free trial
        Route::post('trial/activate', [SubscriptionController::class, 'activateFreeTrial']);

        // Cancel subscription
        Route::post('cancel', [SubscriptionController::class, 'cancel']);

        // Suspend subscription
        Route::post('{subscription}/suspend', [SubscriptionController::class, 'suspendSubscription']);

        // Reactivate subscription
        Route::post('{subscription}/reactivate', [SubscriptionController::class, 'reactivateSubscription']);

        // Update subscription plan
        Route::put('{subscription}/plan', [SubscriptionController::class, 'updateSubscriptionPlan']);

        // Provider-specific routes
        Route::prefix('{provider}')->group(function () {
            // List plans from provider
            Route::get('plans', [SubscriptionController::class, 'listProviderPlans']);

            // Get plan details
            Route::get('plans/{externalPlanId}', [SubscriptionController::class, 'getPlanDetails']);

            // List employer subscriptions
            Route::get('subscriptions', [SubscriptionController::class, 'listEmployerSubscriptions']);

            // Get subscription details
            Route::get('subscriptions/{subscriptionId}', [SubscriptionController::class, 'getSubscriptionDetails']);

            // Get subscription transactions
            Route::get('subscriptions/{subscriptionId}/transactions', [SubscriptionController::class, 'getSubscriptionTransactions']);
        });

        // Payment provider callbacks
        Route::prefix('callbacks')->group(function () {
            // PayPal callbacks
            Route::get('paypal/success', [SubscriptionController::class, 'paypalSuccess']);
            Route::get('paypal/cancel', [SubscriptionController::class, 'paypalCancel']);

            // Stripe callbacks
            Route::get('stripe/success', [SubscriptionController::class, 'stripeSuccess']);
            Route::get('stripe/cancel', [SubscriptionController::class, 'stripeCancel']);
        });

        // Manual verification endpoints
        Route::post('verify-paypal', [SubscriptionController::class, 'verifyPayPalSubscription']);
        Route::post('verify-stripe', [SubscriptionController::class, 'verifyStripeSubscription']);
//        Route::prefix('verify')->group(function () {
//            Route::post('paypal', [SubscriptionController::class, 'verifyPayPalSubscription']);
//            Route::post('stripe', [SubscriptionController::class, 'verifyStripeSubscription']);
//        });
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
        Route::match(['put','patch'],'/{id}', [UsersController::class, 'update']);
        Route::delete('{id}', [UsersController::class, 'delete']);
    });

    // Subscription management
    Route::prefix('subscriptions')->group(function () {
        // Get subscription plans
        Route::get('/plans', [SubscriptionPaymentController::class, 'subscriptionList']);

        // Create payment link
        Route::post('/payment-link/{id}', [SubscriptionPaymentController::class, 'createPaymentLink']);

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

        // Free trial activation (no payment required)
        Route::post('/free-trial', [SubscriptionPaymentController::class, 'activateFreeTrial']);
    });
});

// Subscription callback endpoints (no auth required)
Route::get('subscription/paypal/success', [SubscriptionController::class, 'paypalSuccess']);
Route::get('subscription/paypal/cancel', [SubscriptionController::class, 'paypalCancel']);
Route::get('subscription/stripe/success', [SubscriptionController::class, 'stripeSuccess']);
Route::get('subscription/stripe/cancel', [SubscriptionController::class, 'stripeCancel']);

// Webhook endpoints (no auth required, verified by signature)
Route::post('webhooks/paypal', [PayPalWebhookController::class, 'handle']);
Route::post('webhooks/stripe', [StripeWebhookController::class, 'handle']);
