<?php

use App\Http\Controllers\Candidate\AccountSettingsController;
use App\Http\Controllers\Candidate\CandidateLanguagesController;
use App\Http\Controllers\Candidate\CandidatesController;
use App\Http\Controllers\Candidate\CredentialsController;
use App\Http\Controllers\Candidate\CVsController;
use App\Http\Controllers\Candidate\DashboardController;
use App\Http\Controllers\Candidate\CandidateEducationHistoriesController;
use App\Http\Controllers\Candidate\JobsController;
use App\Http\Controllers\Candidate\UserAccountSettingsController;
use App\Http\Controllers\Candidate\WorkExperiencesController;
use App\Http\Controllers\Candidate\DashboardsController;
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

// Routes that require authentication
Route::middleware(['auth:api', 'role:candidate'])->group(function () {
    // Dashboard
    Route::get('dashboard', [DashboardsController::class, 'index']);
    Route::get('jobs/{type}', [DashboardsController::class, 'getJobs']);

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
        Route::get('/', [WorkExperiencesController::class, 'index']);
        Route::post('/', [WorkExperiencesController::class, 'store']);
        Route::put('/{id}', [WorkExperiencesController::class, 'update']);
        Route::delete('/{id}', [WorkExperiencesController::class, 'delete']);
    });

    // Education history
    Route::prefix('education-history')->group(function () {
        Route::get('/', [CandidateEducationHistoriesController::class, 'index']);
        Route::post('/', [CandidateEducationHistoriesController::class, 'store']);
        Route::put('/{id}', [CandidateEducationHistoriesController::class, 'update']);
        Route::delete('/{id}', [CandidateEducationHistoriesController::class, 'delete']);
    });

    // Credentials
    Route::prefix('credential')->group(function () {
        Route::get('/', [CredentialsController::class, 'index']);
        Route::post('/', [CredentialsController::class, 'store']);
        Route::put('/{id}', [CredentialsController::class, 'update']);
        Route::delete('/{id}', [CredentialsController::class, 'delete']);
    });

    // Languages
    Route::prefix('language')->group(function () {
        Route::get('/', [CandidateLanguagesController::class, 'index']);
        Route::post('/', [CandidateLanguagesController::class, 'store']);
        Route::put('/{id}', [CandidateLanguagesController::class, 'update']);
        Route::delete('/{id}', [CandidateLanguagesController::class, 'delete']);
    });

    // Jobs
    Route::prefix('job')->group(function () {
        Route::get('/', [JobsController::class, 'index']);
        Route::get('{id}/detail', [JobsController::class, 'show']);
        Route::post('{id}/saveJob', [JobsController::class, 'saveJob']);
        Route::post('applyJob', [JobsController::class, 'applyJob']);
        Route::post('applications/{id}/withdraw', [JobsController::class, 'withdrawApplication']);
        Route::get('{id}/similarJobs', [JobsController::class, 'similarJobs']);
        Route::post('{id}/reportJob', [JobsController::class, 'reportJob']);

        // New endpoints
        Route::get('saved', [JobsController::class, 'savedJobs']);
        Route::get('applied', [JobsController::class, 'appliedJobs']);
        Route::get('recommended', [JobsController::class, 'recommendedJobs']);
    });
});
