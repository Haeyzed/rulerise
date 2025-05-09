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
     * Get all contacts.
     *
     * @return JsonResponse
     */
    public function getAllContacts(): JsonResponse
    {
        $contacts = $this->websiteService->getAllContacts();

        return response()->success(
            ContactResource::collection($contacts),
            'Contacts retrieved successfully'
        );
    }

    /**
     * Get a specific contact by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getContact(int $id): JsonResponse
    {
        $contact = $this->websiteService->getContact($id);

        return response()->success(
            new ContactResource($contact),
            'Contact retrieved successfully'
        );
    }

    /**
     * Get all website sections (hero, about us) in a single request.
     * Contacts are excluded as they are multiple records.
     *
     * @return JsonResponse
     */
    public function getAllSections(): JsonResponse
    {
        $heroSection = $this->websiteService->getHeroSection();
        $aboutUs = $this->websiteService->getAboutUs();
        $contacts = $this->websiteService->getAllContacts();

        return response()->success([
            'hero_section' => new HeroSectionResource($heroSection),
            'about_us' => new AboutUsResource($aboutUs),
            'contacts' => ContactResource::collection($contacts),
        ], 'Website sections retrieved successfully');
    }
}
