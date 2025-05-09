<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AboutUsRequest;
use App\Http\Requests\Admin\AdBannerRequest;
use App\Http\Requests\Admin\ContactRequest;
use App\Http\Requests\Admin\HeroSectionRequest;
use App\Http\Resources\AboutUsResource;
use App\Http\Resources\AdBannerResource;
use App\Http\Resources\ContactResource;
use App\Http\Resources\HeroSectionResource;
use App\Services\Storage\StorageService;
use App\Services\WebsiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class WebsiteController extends Controller implements HasMiddleware
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
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:admin']),
        ];
    }

    /**
     * Get the hero section.
     *
     * @return JsonResponse
     */
    public function getHeroSection(): JsonResponse
    {
        $heroSection = $this->websiteService->getHeroSection();

        return response()->success(new HeroSectionResource($heroSection),
            'Hero section retrieved successfully'
        );
    }

    /**
     * Create or update the hero section.
     *
     * @param HeroSectionRequest $request
     * @return JsonResponse
     * @throws \Throwable
     */
    public function createOrUpdateHeroSection(HeroSectionRequest $request): JsonResponse
    {
        $heroSection = $this->websiteService->createOrUpdateHeroSection($request->validated());

        return response()->success(
            new HeroSectionResource($heroSection),
            'Hero section updated successfully'
        );
    }

    /**
     * Delete a hero section image.
     *
     * @param int $imageId
     * @return JsonResponse
     */
    public function deleteHeroSectionImage(int $imageId): JsonResponse
    {
        $this->websiteService->deleteHeroSectionImage($imageId);

        return response()->success(null, 'Hero section image deleted successfully');
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
     * Create or update the about us section.
     *
     * @param AboutUsRequest $request
     * @return JsonResponse
     */
    public function createOrUpdateAboutUs(AboutUsRequest $request): JsonResponse
    {
        $aboutUs = $this->websiteService->createOrUpdateAboutUs($request->validated());

        return response()->success(
            new AboutUsResource($aboutUs),
            'About us section updated successfully'
        );
    }

    /**
     * Delete an about us image.
     *
     * @param int $imageId
     * @return JsonResponse
     */
    public function deleteAboutUsImage(int $imageId): JsonResponse
    {
        $this->websiteService->deleteAboutUsImage($imageId);

        return response()->success(null, 'About us image deleted successfully');
    }

    /**
     * Get a contact by ID.
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
     * Create or update a contact.
     *
     * @param ContactRequest $request
     * @param int|null $id
     * @return JsonResponse
     * @throws \Throwable
     */
    public function createOrUpdateContact(ContactRequest $request, ?int $id = null): JsonResponse
    {
        $contact = $this->websiteService->createOrUpdateContact($request->validated(), $id);

        return response()->success(
            new ContactResource($contact),
            $id ? 'Contact updated successfully' : 'Contact created successfully'
        );
    }

    /**
     * Delete a contact.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteContact(int $id): JsonResponse
    {
        $this->websiteService->deleteContact($id);

        return response()->success(null, 'Contact deleted successfully');
    }

    /**
     * Get all ad banners.
     *
     * @return JsonResponse
     */
    public function getAllAdBanners(): JsonResponse
    {
        $adBanners = $this->websiteService->getAllAdBanners();

        return response()->success(
            AdBannerResource::collection($adBanners),
            'Ad banners retrieved successfully'
        );
    }

    /**
     * Get an ad banner.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAdBanner(int $id): JsonResponse
    {
        $adBanner = $this->websiteService->getAdBanner($id);

        return response()->success(
            new AdBannerResource($adBanner),
            'Ad banner retrieved successfully'
        );
    }

    /**
     * Create or update an ad banner.
     *
     * @param AdBannerRequest $request
     * @param int|null $id
     * @return JsonResponse
     */
    public function createOrUpdateAdBanner(AdBannerRequest $request, ?int $id = null): JsonResponse
    {
        $adBanner = $this->websiteService->createOrUpdateAdBanner($request->validated(), $id);

        return response()->success(
            new AdBannerResource($adBanner),
            $id ? 'Ad banner updated successfully' : 'Ad banner created successfully'
        );
    }

    /**
     * Delete an ad banner.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteAdBanner(int $id): JsonResponse
    {
        $this->websiteService->deleteAdBanner($id);

        return response()->success(null, 'Ad banner deleted successfully');
    }

    /**
     * Delete an ad banner image.
     *
     * @param int $adBannerId
     * @param int $imageId
     * @return JsonResponse
     */
    public function deleteAdBannerImage(int $adBannerId, int $imageId): JsonResponse
    {
        $this->websiteService->deleteAdBannerImage($adBannerId, $imageId);

        return response()->success(null, 'Ad banner image deleted successfully');
    }
}
