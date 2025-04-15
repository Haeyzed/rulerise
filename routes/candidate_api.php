<?php

use App\Http\Controllers\Candidate\AccountSettingsController;
use App\Http\Controllers\Candidate\CandidateLanguagesController;
use App\Http\Controllers\Candidate\CandidatesController;
use App\Http\Controllers\Candidate\CredentialsController;
use App\Http\Controllers\Candidate\CVsController;
use App\Http\Controllers\Candidate\DashboardController;
use App\Http\Controllers\Candidate\EducationHistoriesController;
use App\Http\Controllers\Candidate\JobsController;
use App\Http\Controllers\Candidate\UserAccountSettingsController;
use App\Http\Controllers\Candidate\WorkExperiencesController;
use App\Http\Controllers\Public\MetaInformationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Candidate API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for candidate users. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Auth routes (no auth required)
//Route::group(['prefix' => 'auth'], function () {
//    Route::post('register', [AuthController::class, 'register']);
//    Route::post('resendEmailVerification/{email}', [AuthController::class, 'resendEmailVerification']);
//    Route::post('verifyEmail', [AuthController::class, 'verifyEmail']);
//    Route::post('login', [AuthController::class, 'login']);
//    Route::post('forgot-password/{email}', [AuthController::class, 'sendResetPasswordLink']);
//    Route::post('verify-forgot-password', [AuthController::class, 'verifyResetPasswordLink']);
//    Route::post('reset-password', [AuthController::class, 'resetPassword']);
//});

// Routes that require authentication
Route::middleware(['auth:api', 'role:candidate'])->group(function () {
    // Dashboard
    Route::get('metrics', [DashboardController::class, 'metrics']);

    // Meta information
    Route::get('languageProficiency', [MetaInformationController::class, 'languageProficiency']);

    // Profile
    Route::get('profile', [CandidatesController::class, 'getProfile']);
    Route::post('update-profile', [CandidatesController::class, 'updateProfile']);

    // Account settings
    Route::get('account-setting', [AccountSettingsController::class, 'index']);
    Route::post('update-account-setting', [AccountSettingsController::class, 'updateAccountSetting']);
    Route::post('delete-account', [AccountSettingsController::class, 'deleteAccount']);
//    Route::post('change-password', [AuthController::class, 'changePassword']);
    Route::post('upload-profile-picture', [UserAccountSettingsController::class, 'uploadProfilePicture']);

    // CV
    Route::prefix('cv')->group(function () {
        Route::post('upload', [CVsController::class, 'uploadCv']);
        Route::get('detail', [CVsController::class, 'cvDetail']);
        Route::post('{id}/delete', [CVsController::class, 'delete']);
    });

    // Work experience
    Route::prefix('work-experience')->group(function () {
        Route::post('/', [WorkExperiencesController::class, 'store']);
        Route::post('update', [WorkExperiencesController::class, 'update']);
        Route::post('{id}/delete', [WorkExperiencesController::class, 'delete']);
    });

    // Education history
    Route::prefix('education-history')->group(function () {
        Route::post('/', [EducationHistoriesController::class, 'store']);
        Route::post('update', [EducationHistoriesController::class, 'update']);
        Route::post('{id}/delete', [EducationHistoriesController::class, 'delete']);
    });

    // Credentials
    Route::prefix('credential')->group(function () {
        Route::post('/', [CredentialsController::class, 'store']);
        Route::post('update', [CredentialsController::class, 'update']);
        Route::post('{id}/delete', [CredentialsController::class, 'delete']);
    });

    // Languages
    Route::prefix('language')->group(function () {
        Route::post('/', [CandidateLanguagesController::class, 'store']);
        Route::post('update', [CandidateLanguagesController::class, 'update']);
        Route::post('{id}/delete', [CandidateLanguagesController::class, 'delete']);
    });

    // Jobs
    Route::prefix('job')->group(function () {
        Route::get('/', [JobsController::class, 'index']);
        Route::get('{id}/detail', [JobsController::class, 'show']);
        Route::post('{id}/saveJob', [JobsController::class, 'saveJob']);
        Route::post('applyJob', [JobsController::class, 'applyJob']);
        Route::get('{id}/similarJobs', [JobsController::class, 'similarJobs']);
        Route::post('{id}/reportJob', [JobsController::class, 'reportJob']);

        // New endpoints
        Route::get('saved', [JobsController::class, 'savedJobs']);
        Route::get('applied', [JobsController::class, 'appliedJobs']);
        Route::get('recommended', [JobsController::class, 'recommendedJobs']);
    });
});
