<?php

namespace App\Services;

use App\Models\AboutUs;
use App\Models\AdBanner;
use App\Models\Contact;
use App\Models\HeroSection;
use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WebsiteService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * WebsiteService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Get the hero section or create a new one if none exists.
     *
     * @return HeroSection
     */
    public function getHeroSection(): HeroSection
    {
        return HeroSection::with(['images'])
            ->first() ?? new HeroSection();
    }

    /**
     * Create or update the hero section.
     * Only one record will be maintained.
     *
     * @param array $data The validated data for creating/updating a hero section.
     * @return HeroSection The created or updated hero section.
     * @throws \Throwable
     */
    public function createOrUpdateHeroSection(array $data): HeroSection
    {
        return DB::transaction(function () use ($data) {
            // Handle main image
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image_path'] = $this->uploadImage(
                    $data['image'],
                    config('filestorage.paths.hero_images', 'hero_images'),
                );
                unset($data['image']);
            }

            // Get existing hero section or create new one
            $heroSection = HeroSection::first();
            $isNew = false;

            if (!$heroSection) {
                $heroSection = new HeroSection();
                $isNew = true;
            } elseif (isset($data['image_path']) && $heroSection->image_path) {
                // If updating and there's a new image, delete the old one
                $this->storageService->delete($heroSection->image_path);
            }

            $heroSection->fill($data);
            $heroSection->save();

            // Handle related images
            if (isset($data['images']) && is_array($data['images'])) {
                $this->handleHeroSectionImages($heroSection, $data['images']);
            }

            return $heroSection->load(['images']);
        });
    }

    /**
     * Delete a hero section image.
     *
     * @param int $imageId The ID of the image to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteHeroSectionImage(int $imageId): bool
    {
        return DB::transaction(function () use ($imageId) {
            $heroSection = $this->getHeroSection();
            $image = $heroSection->images()->findOrFail($imageId);

            // Delete the image file
            $this->storageService->delete($image->image_path);

            // Delete the image record
            return $image->delete();
        });
    }

    /**
     * Handle related images for a hero section.
     *
     * @param HeroSection $heroSection The hero section.
     * @param array $images The array of image files.
     * @return void
     */
    private function handleHeroSectionImages(HeroSection $heroSection, array $images): void
    {
        $order = $heroSection->images()->count() + 1;

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $heroSection->images()->create([
                    'image_path' => $this->uploadImage(
                        $image,
                        config('filestorage.paths.hero_images', 'hero_images'),
                    ),
                    'order' => $order++,
                ]);
            }
        }
    }

    /**
     * Get the about us section or create a new one if none exists.
     *
     * @return AboutUs
     */
    public function getAboutUs(): AboutUs
    {
        return AboutUs::with(['images'])->first() ?? new AboutUs();
    }

    /**
     * Create or update the about us section.
     * Only one record will be maintained.
     *
     * @param array $data The validated data for creating/updating the about us section.
     * @return AboutUs The created or updated about us section.
     */
    public function createOrUpdateAboutUs(array $data): AboutUs
    {
        return DB::transaction(function () use ($data) {
            // Get existing about us or create new one
            $aboutUs = AboutUs::first();

            if (!$aboutUs) {
                $aboutUs = new AboutUs();
            }

            $aboutUs->fill($data);
            $aboutUs->save();

            // Handle related images
            if (isset($data['images']) && is_array($data['images'])) {
                $this->handleAboutUsImages($aboutUs, $data['images']);
            }

            return $aboutUs->load(['images']);
        });
    }

    /**
     * Delete an about us image.
     *
     * @param int $imageId The ID of the image to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteAboutUsImage(int $imageId): bool
    {
        return DB::transaction(function () use ($imageId) {
            $aboutUs = $this->getAboutUs();
            $image = $aboutUs->images()->findOrFail($imageId);

            // Delete the image file
            $this->storageService->delete($image->image_path);

            // Delete the image record
            return $image->delete();
        });
    }

    /**
     * Handle related images for an about us section.
     *
     * @param AboutUs $aboutUs The about us section.
     * @param array $images The array of image files.
     * @return void
     */
    private function handleAboutUsImages(AboutUs $aboutUs, array $images): void
    {
        $order = $aboutUs->images()->count() + 1;

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $aboutUs->images()->create([
                    'image_path' => $this->uploadImage(
                        $image,
                        config('filestorage.paths.about_us_images', 'about_us_images'),
                    ),
                    'order' => $order++,
                ]);
            }
        }
    }

    /**
     * Get a contact by ID.
     *
     * @param int $id The ID of the contact.
     * @return Contact
     */
    public function getContact(int $id): Contact
    {
        return Contact::findOrFail($id);
    }

    /**
     * Get all contacts.
     *
     * @return Collection
     */
    public function getAllContacts(): Collection
    {
        return Contact::orderBy('order', 'asc')->get();
    }

    /**
     * Create or update a contact.
     *
     * @param array $data The validated data for creating/updating a contact.
     * @param int|null $id The ID of the contact to update, or null to create a new one.
     * @return Contact The created or updated contact.
     * @throws \Throwable
     */
    public function createOrUpdateContact(array $data, ?int $id = null): Contact
    {
        return DB::transaction(function () use ($data, $id) {
            // Create or update contact
            $contact = $id
                ? Contact::findOrFail($id)
                : new Contact();

            $contact->fill($data);
            $contact->save();

            return $contact;
        });
    }

    /**
     * Delete a contact.
     *
     * @param int $id The ID of the contact to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteContact(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $contact = Contact::findOrFail($id);
            return $contact->delete();
        });
    }

    /**
     * Get all ad banners.
     *
     * @return Collection
     */
    public function getAllAdBanners(): Collection
    {
        return AdBanner::with(['images'])
            ->orderBy('order', 'asc')
            ->get();
    }

    /**
     * Get an ad banner by ID.
     *
     * @param int $id The ID of the ad banner.
     * @return AdBanner
     */
    public function getAdBanner(int $id): AdBanner
    {
        return AdBanner::with(['images'])->findOrFail($id);
    }

    /**
     * Create or update an ad banner.
     *
     * @param array $data The validated data for creating/updating an ad banner.
     * @param int|null $id The ID of the ad banner to update, or null to create a new one.
     * @return AdBanner The created or updated ad banner.
     */
    public function createOrUpdateAdBanner(array $data, ?int $id = null): AdBanner
    {
        return DB::transaction(function () use ($data, $id) {
            // Handle main image
            if (isset($data['image']) && $data['image'] instanceof UploadedFile) {
                $data['image_path'] = $this->uploadImage(
                    $data['image'],
                    config('filestorage.paths.ad_banner_images', 'ad_banner_images'),
                );
                unset($data['image']);
            }

            // Create or update ad banner
            $adBanner = $id
                ? AdBanner::findOrFail($id)
                : new AdBanner();

            // If updating and there's a new image, delete the old one
            if ($id && isset($data['image_path']) && $adBanner->image_path) {
                $this->storageService->delete($adBanner->image_path);
            }

            $adBanner->fill($data);
            $adBanner->save();

            // Handle related images
            if (isset($data['images']) && is_array($data['images'])) {
                $this->handleAdBannerImages($adBanner, $data['images']);
            }

            return $adBanner->load(['images']);
        });
    }

    /**
     * Delete an ad banner.
     *
     * @param int $id The ID of the ad banner to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteAdBanner(int $id): bool
    {
        return DB::transaction(function () use ($id) {
            $adBanner = AdBanner::findOrFail($id);

            // Delete main image
            if ($adBanner->image_path) {
                $this->storageService->delete($adBanner->image_path);
            }

            // Delete related images
            foreach ($adBanner->images as $image) {
                $this->storageService->delete($image->image_path);
                $image->delete();
            }

            return $adBanner->delete();
        });
    }

    /**
     * Delete an ad banner image.
     *
     * @param int $adBannerId The ID of the ad banner.
     * @param int $imageId The ID of the image to delete.
     * @return bool Whether the deletion was successful.
     */
    public function deleteAdBannerImage(int $adBannerId, int $imageId): bool
    {
        return DB::transaction(function () use ($adBannerId, $imageId) {
            $adBanner = AdBanner::findOrFail($adBannerId);
            $image = $adBanner->images()->findOrFail($imageId);

            // Delete the image file
            $this->storageService->delete($image->image_path);

            // Delete the image record
            return $image->delete();
        });
    }

    /**
     * Handle related images for an ad banner.
     *
     * @param AdBanner $adBanner The ad banner.
     * @param array $images The array of image files.
     * @return void
     */
    private function handleAdBannerImages(AdBanner $adBanner, array $images): void
    {
        $order = $adBanner->images()->count() + 1;

        foreach ($images as $image) {
            if ($image instanceof UploadedFile) {
                $adBanner->images()->create([
                    'image_path' => $this->uploadImage(
                        $image,
                        config('filestorage.paths.ad_banner_images', 'ad_banner_images'),
                    ),
                    'order' => $order++,
                ]);
            }
        }
    }

    /**
     * Upload an image to storage.
     *
     * @param UploadedFile $image The image file to upload.
     * @param string $path The storage path.
     * @param string|null $fileName The name to store the file as.
     * @param array $options Additional options for the upload.
     * @return string The path to the uploaded image.
     */
    private function uploadImage(UploadedFile $image, string $path, ?string &$fileName = null, array $options = []): string
    {
        // Generate a filename based on the current timestamp and a random string
        $fileName = time() . '-' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

        return $this->storageService->upload($image, $path, $fileName, $options);
    }
}
