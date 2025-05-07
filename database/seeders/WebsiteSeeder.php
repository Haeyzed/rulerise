<?php

namespace Database\Seeders;

use App\Models\AboutUs;
use App\Models\AboutUsImage;
use App\Models\AdBanner;
use App\Models\AdBannerImage;
use App\Models\Contact;
use App\Models\HeroSection;
use App\Models\HeroSectionImage;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WebsiteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding website content...');

        // Create storage directories if they don't exist
        $this->createStorageDirectories();

        // Seed Hero Sections
        $this->seedHeroSections();

        // Seed About Us
        $this->seedAboutUs();

        // Seed Contacts
        $this->seedContacts();

        // Seed Ad Banners
        $this->seedAdBanners();

        $this->command->info('Website content seeded successfully!');
    }

    /**
     * Create necessary storage directories.
     */
    private function createStorageDirectories(): void
    {
        $directories = [
            'hero_images',
            'about_us_images',
            'ad_banner_images',
        ];

        foreach ($directories as $directory) {
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
                $this->command->info("Created directory: {$directory}");
            }
        }
    }

    /**
     * Seed hero sections.
     */
    private function seedHeroSections(): void
    {
        $this->command->info('Seeding hero sections...');

        // Clear existing data
        HeroSectionImage::query()->delete();
        HeroSection::query()->delete();

        // Create hero sections
        $heroSections = [
            [
                'title' => 'Find Your Dream Job Today',
                'subtitle' => 'Connect with top employers and discover opportunities that match your skills',
                'order' => 1,
                'is_active' => true,
                'image_path' => $this->copyDummyImage('hero_images', 'hero-1.jpg'),
            ],
            [
                'title' => 'Hire Top Talent',
                'subtitle' => 'Find qualified candidates for your open positions quickly and efficiently',
                'order' => 2,
                'is_active' => true,
                'image_path' => $this->copyDummyImage('hero_images', 'hero-2.jpg'),
            ],
            [
                'title' => 'Career Growth & Development',
                'subtitle' => 'Resources and opportunities to advance your professional journey',
                'order' => 3,
                'is_active' => true,
                'image_path' => $this->copyDummyImage('hero_images', 'hero-3.jpg'),
            ],
        ];

        foreach ($heroSections as $sectionData) {
            $heroSection = HeroSection::create($sectionData);
            
            // Add additional images for each hero section
            for ($i = 1; $i <= 2; $i++) {
                HeroSectionImage::create([
                    'hero_section_id' => $heroSection->id,
                    'image_path' => $this->copyDummyImage('hero_images', "hero-section-{$heroSection->id}-image-{$i}.jpg"),
                    'order' => $i,
                ]);
            }
        }

        $this->command->info('Hero sections seeded successfully!');
    }

    /**
     * Seed about us section.
     */
    private function seedAboutUs(): void
    {
        $this->command->info('Seeding about us section...');

        // Clear existing data
        AboutUsImage::query()->delete();
        AboutUs::query()->delete();

        // Create about us section
        $aboutUs = AboutUs::create([
            'headline' => 'About Our Job Platform',
            'sub_headline' => 'Connecting talent with opportunity since 2015',
            'body' => '<p>Welcome to our job platform, where we\'ve been bridging the gap between talented professionals and forward-thinking companies for over 8 years.</p>
                      <p>Our mission is to create a seamless job search experience that benefits both job seekers and employers. We believe that the right job match can transform lives and businesses.</p>
                      <p>What sets us apart:</p>
                      <ul>
                          <li>Personalized job recommendations based on your skills and preferences</li>
                          <li>Advanced matching algorithms to connect employers with qualified candidates</li>
                          <li>Comprehensive resources for career development and growth</li>
                          <li>Dedicated support team to assist throughout the hiring process</li>
                      </ul>
                      <p>Join thousands of professionals who have found their dream jobs through our platform, and hundreds of companies who have built stronger teams with our help.</p>',
            'is_active' => true,
        ]);

        // Add images for about us section
        $aboutUsImages = [
            [
                'about_us_id' => $aboutUs->id,
                'image_path' => $this->copyDummyImage('about_us_images', 'about-us-1.jpg'),
                'order' => 1,
            ],
            [
                'about_us_id' => $aboutUs->id,
                'image_path' => $this->copyDummyImage('about_us_images', 'about-us-2.jpg'),
                'order' => 2,
            ],
            [
                'about_us_id' => $aboutUs->id,
                'image_path' => $this->copyDummyImage('about_us_images', 'about-us-3.jpg'),
                'order' => 3,
            ],
        ];

        foreach ($aboutUsImages as $imageData) {
            AboutUsImage::create($imageData);
        }

        $this->command->info('About us section seeded successfully!');
    }

    /**
     * Seed contacts.
     */
    private function seedContacts(): void
    {
        $this->command->info('Seeding contacts...');

        // Clear existing data
        Contact::query()->delete();

        // Create contacts
        $contacts = [
            [
                'title' => 'Email Us',
                'value' => 'contact@jobplatform.com',
                'type' => 'email',
                'order' => 1,
                'is_active' => true,
            ],
            [
                'title' => 'Call Us',
                'value' => '+1 (555) 123-4567',
                'type' => 'phone',
                'order' => 2,
                'is_active' => true,
            ],
            [
                'title' => 'WhatsApp',
                'value' => '+1 (555) 987-6543',
                'type' => 'whatsapp',
                'order' => 3,
                'is_active' => true,
            ],
            [
                'title' => 'LinkedIn',
                'value' => 'https://linkedin.com/company/jobplatform',
                'type' => 'linkedin',
                'order' => 4,
                'is_active' => true,
            ],
            [
                'title' => 'Facebook',
                'value' => 'https://facebook.com/jobplatform',
                'type' => 'facebook',
                'order' => 5,
                'is_active' => true,
            ],
            [
                'title' => 'Instagram',
                'value' => 'https://instagram.com/jobplatform',
                'type' => 'instagram',
                'order' => 6,
                'is_active' => true,
            ],
            [
                'title' => 'Twitter',
                'value' => 'https://twitter.com/jobplatform',
                'type' => 'link',
                'order' => 7,
                'is_active' => true,
            ],
            [
                'title' => 'Office Address',
                'value' => '123 Business Avenue, Suite 500, San Francisco, CA 94107',
                'type' => 'link',
                'order' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($contacts as $contactData) {
            Contact::create($contactData);
        }

        $this->command->info('Contacts seeded successfully!');
    }

    /**
     * Seed ad banners.
     */
    private function seedAdBanners(): void
    {
        $this->command->info('Seeding ad banners...');

        // Clear existing data
        AdBannerImage::query()->delete();
        AdBanner::query()->delete();

        // Create ad banners
        $adBanners = [
            [
                'title' => 'Premium Membership Offer',
                'link' => 'https://jobplatform.com/premium',
                'image_path' => $this->copyDummyImage('ad_banner_images', 'ad-banner-1.jpg'),
                'order' => 1,
                'is_active' => true,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(3),
            ],
            [
                'title' => 'Virtual Job Fair',
                'link' => 'https://jobplatform.com/job-fair',
                'image_path' => $this->copyDummyImage('ad_banner_images', 'ad-banner-2.jpg'),
                'order' => 2,
                'is_active' => true,
                'start_date' => Carbon::now()->addDays(15),
                'end_date' => Carbon::now()->addMonths(2),
            ],
            [
                'title' => 'Resume Review Service',
                'link' => 'https://jobplatform.com/resume-review',
                'image_path' => $this->copyDummyImage('ad_banner_images', 'ad-banner-3.jpg'),
                'order' => 3,
                'is_active' => true,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(6),
            ],
        ];

        foreach ($adBanners as $bannerData) {
            $adBanner = AdBanner::create($bannerData);
            
            // Add additional images for each ad banner
            for ($i = 1; $i <= 2; $i++) {
                AdBannerImage::create([
                    'ad_banner_id' => $adBanner->id,
                    'image_path' => $this->copyDummyImage('ad_banner_images', "ad-banner-{$adBanner->id}-image-{$i}.jpg"),
                    'order' => $i,
                ]);
            }
        }

        $this->command->info('Ad banners seeded successfully!');
    }

    /**
     * Copy a dummy image to the storage directory.
     *
     * @param string $directory The directory to copy the image to.
     * @param string $filename The filename to use.
     * @return string The path to the copied image.
     */
    private function copyDummyImage(string $directory, string $filename): string
    {
        // Generate a placeholder image path
        $path = "{$directory}/{$filename}";
        
        // Create a placeholder image (in a real scenario, you would copy from a source)
        $this->generatePlaceholderImage($path);
        
        return $path;
    }

    /**
     * Generate a placeholder image.
     *
     * @param string $path The path to save the image to.
     * @return void
     */
    private function generatePlaceholderImage(string $path): void
    {
        // In a real scenario, you would copy from a source directory
        // For this example, we'll just create a text file as a placeholder
        $content = "This is a placeholder for an image at {$path}";
        Storage::put($path, $content);
    }
}
