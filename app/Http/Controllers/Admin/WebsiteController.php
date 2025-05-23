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
use App\Services\AdminAclService;
use App\Services\Storage\StorageService;
use App\Services\WebsiteService;
use Exception;
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
     * The Admin ACL service instance.
     *
     * @var AdminAclService
     */
    protected AdminAclService $adminAclService;

    /**
     * Create a new controller instance.
     *
     * @param WebsiteService $websiteService
     * @param AdminAclService $adminAclService
     * @return void
     */
    public function __construct(WebsiteService $websiteService, AdminAclService $adminAclService)
    {
        $this->websiteService = $websiteService;
        $this->adminAclService = $adminAclService;
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $heroSection = $this->websiteService->getHeroSection();

            return response()->success(new HeroSectionResource($heroSection),
                'Hero section retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $heroSection = $this->websiteService->createOrUpdateHeroSection($request->validated());

            return response()->success(
                new HeroSectionResource($heroSection),
                'Hero section updated successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete a hero section image.
     *
     * @param int $imageId
     * @return JsonResponse
     */
    public function deleteHeroSectionImage(int $imageId): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->websiteService->deleteHeroSectionImage($imageId);

            return response()->success(null, 'Hero section image deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get the about us section.
     *
     * @return JsonResponse
     */
    public function getAboutUs(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $aboutUs = $this->websiteService->getAboutUs();

            return response()->success(
                new AboutUsResource($aboutUs),
                'About us section retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Create or update the about us section.
     *
     * @param AboutUsRequest $request
     * @return JsonResponse
     */
    public function createOrUpdateAboutUs(AboutUsRequest $request): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('update');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $aboutUs = $this->websiteService->createOrUpdateAboutUs($request->validated());

            return response()->success(
                new AboutUsResource($aboutUs),
                'About us section updated successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete an about us image.
     *
     * @param int $imageId
     * @return JsonResponse
     */
    public function deleteAboutUsImage(int $imageId): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->websiteService->deleteAboutUsImage($imageId);

            return response()->success(null, 'About us image deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get a contact by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getContact(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $contact = $this->websiteService->getContact($id);

            return response()->success(
                new ContactResource($contact),
                'Contact retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get all contacts.
     *
     * @return JsonResponse
     */
    public function getAllContacts(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $contacts = $this->websiteService->getAllContacts();

            return response()->success(
                ContactResource::collection($contacts),
                'Contacts retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission($id ? 'update' : 'create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $contact = $this->websiteService->createOrUpdateContact($request->validated(), $id);

            return response()->success(
                new ContactResource($contact),
                $id ? 'Contact updated successfully' : 'Contact created successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete a contact.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteContact(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->websiteService->deleteContact($id);

            return response()->success(null, 'Contact deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get all ad banners.
     *
     * @return JsonResponse
     */
    public function getAllAdBanners(): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $adBanners = $this->websiteService->getAllAdBanners();

            return response()->success(
                AdBannerResource::collection($adBanners),
                'Ad banners retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Get an ad banner.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function getAdBanner(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('view');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $adBanner = $this->websiteService->getAdBanner($id);

            return response()->success(
                new AdBannerResource($adBanner),
                'Ad banner retrieved successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission($id ? 'update' : 'create');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $adBanner = $this->websiteService->createOrUpdateAdBanner($request->validated(), $id);

            return response()->success(
                new AdBannerResource($adBanner),
                $id ? 'Ad banner updated successfully' : 'Ad banner created successfully'
            );
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }

    /**
     * Delete an ad banner.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function deleteAdBanner(int $id): JsonResponse
    {
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->websiteService->deleteAdBanner($id);

            return response()->success(null, 'Ad banner deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
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
        try {
            // Check permission using AdminAclService
            [$hasPermission, $errorMessage] = $this->adminAclService->hasPermission('delete');
            if (!$hasPermission) {
                return response()->forbidden($errorMessage);
            }

            $this->websiteService->deleteAdBannerImage($adBannerId, $imageId);

            return response()->success(null, 'Ad banner image deleted successfully');
        } catch (Exception $e) {
            return response()->serverError($e->getMessage());
        }
    }
}
