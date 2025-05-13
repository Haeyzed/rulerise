<?php

use App\Http\Controllers\Public\BlogPostController;
use App\Http\Controllers\Public\CandidatesController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\JobCategoriesController;
use App\Http\Controllers\Public\JobsController;
use App\Http\Controllers\Public\WebsiteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Public\FrontPagesController;
use App\Http\Controllers\Public\MetaInformationController;
use App\Http\Controllers\Public\EmployersController;

/*
|--------------------------------------------------------------------------
| Public API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for public access. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// Front page
//Route::get('front-page', [FrontPagesController::class, 'index']);

// Website Sections
Route::prefix('website')->group(function () {
    Route::get('/', [WebsiteController::class, 'getAllSections']);
    Route::get('/hero-section', [WebsiteController::class, 'getHeroSection']);
    Route::get('/about-us', [WebsiteController::class, 'getAboutUs']);
    Route::get('/contact', [WebsiteController::class, 'getContact']);
    Route::get('/contacts', [WebsiteController::class, 'getAllContacts']);
});

// Jobs
Route::get('search-jobs', [JobsController::class, 'searchJobs']);
Route::get('similar-jobs', [JobsController::class, 'similarJobs']);
Route::get('latest-jobs', [JobsController::class, 'latestJobs']);
Route::get('featured-jobs', [JobsController::class, 'featuredJobs']);
Route::get('job/{id}', [JobsController::class, 'show']);

// Job categories
//Route::get('job-categories', [MetaInformationController::class, 'getJobCategory']);
//Route::get('job-categories/{id}', [MetaInformationController::class, 'getSingleCategory']);

// Update the job categories routes
Route::get('job-categories', [JobCategoriesController::class, 'index']);
Route::get('job-categories/featured', [JobCategoriesController::class, 'featured']);
Route::get('job-categories/popular', [JobCategoriesController::class, 'popular']);
Route::get('job-categories/{id}', [JobCategoriesController::class, 'show']);

// Employers
Route::get('employers', [EmployersController::class, 'index']);
Route::get('employers/{id}', [EmployersController::class, 'show']);

// Candidates
Route::get('candidate-profile/{id}', [CandidatesController::class, 'show']);

Route::prefix('faqs')->group(function () {
    Route::get('/', [FaqController::class, 'getAllFaqs']);
    Route::get('/categories', [FaqController::class, 'getAllCategories']);
    Route::get('/categories/{slugOrId}', [FaqController::class, 'getCategory']);
    Route::get('/search', [FaqController::class, 'search']);
});

// Blog Posts
Route::get('blog-posts', [BlogPostController::class, 'index'])->name('blog-posts.index');
Route::get('blog-posts/{blogPost}', [BlogPostController::class, 'show'])->name('blog-posts.show');
