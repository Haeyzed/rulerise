<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\AboutUsResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\HeroSectionResource;
use App\Services\WebsiteService;
use Illuminate\Http\JsonResponse;

class WebsiteController extends Controller
{
    /**
     * Website service instance
     *
     * @var WebsiteService
     */
    protected WebsiteService $websiteService;

    /**
     * Create a new controller instance.
     *
     * @param WebsiteService $websiteService
     * @return void
     */
    public function __construct(WebsiteService $websiteService)
    {
        $this->websiteService = $websiteService;
    }

    /**
     * Get the hero section.
     *
     * @return JsonResponse
     */
    public function getHeroSection(): JsonResponse
    {
        $heroSection = $this->websiteService->getHeroSection();

        return response()->success(
            new HeroSectionResource($heroSection),
            'Hero section retrieved successfully'
        );
    }

    /**
     * Get the about us section.
     *
     * @return JsonResponse
     */
    public function getAboutUs(): JsonResponse
    {
        $aboutUs = $this->websiteService->getAboutUs();

        return response()->success(
            new AboutUsResource($aboutUs),
            'About us section retrieved successfully'
        );
    }

    /**
     * Get the contact information.
     *
     * @return JsonResponse
     */
    public function getContact(): JsonResponse
    {
        $contact = $this->websiteService->getContact();

        return response()->success(
            new ContactResource($contact),
            'Contact information retrieved successfully'
        );
    }

    /**
     * Get all website sections (hero, about us, contact) in a single request.
     *
     * @return JsonResponse
     */
    public function getAllSections(): JsonResponse
    {
        $heroSection = $this->websiteService->getHeroSection();
        $aboutUs = $this->websiteService->getAboutUs();
        $contact = $this->websiteService->getContact();

        return response()->success([
            'hero_section' => new HeroSectionResource($heroSection),
            'about_us' => new AboutUsResource($aboutUs),
            'contact' => new ContactResource($contact),
        ], 'Website sections retrieved successfully');
    }
}
