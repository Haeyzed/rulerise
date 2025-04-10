<?php

namespace Database\Seeders;

use App\Models\JobCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class JobCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Construction',
                'description' => 'Jobs related to building, renovation, and construction projects',
                'icon' => 'building',
                'is_active' => true,
            ],
            [
                'name' => 'Healthcare aide/Caregiver',
                'description' => 'Jobs related to healthcare support and caregiving services',
                'icon' => 'heart-pulse',
                'is_active' => true,
            ],
            [
                'name' => 'Farming & Agriculture',
                'description' => 'Jobs related to farming, agriculture, and food production',
                'icon' => 'wheat',
                'is_active' => true,
            ],
            [
                'name' => 'Transportation',
                'description' => 'Jobs related to transportation, logistics, and delivery services',
                'icon' => 'truck',
                'is_active' => true,
            ],
            [
                'name' => 'Information Technology',
                'description' => 'Jobs related to software development, IT support, and technology services',
                'icon' => 'code',
                'is_active' => true,
            ],
            [
                'name' => 'Education',
                'description' => 'Jobs related to teaching, training, and educational services',
                'icon' => 'graduation-cap',
                'is_active' => true,
            ],
            [
                'name' => 'Finance & Accounting',
                'description' => 'Jobs related to financial services, accounting, and banking',
                'icon' => 'landmark',
                'is_active' => true,
            ],
            [
                'name' => 'Sales & Marketing',
                'description' => 'Jobs related to sales, marketing, and business development',
                'icon' => 'megaphone',
                'is_active' => true,
            ],
            [
                'name' => 'Hospitality & Tourism',
                'description' => 'Jobs related to hotels, restaurants, tourism, and customer service',
                'icon' => 'utensils',
                'is_active' => true,
            ],
            [
                'name' => 'Manufacturing',
                'description' => 'Jobs related to production, assembly, and manufacturing operations',
                'icon' => 'factory',
                'is_active' => true,
            ],
            [
                'name' => 'Retail',
                'description' => 'Jobs related to retail stores, merchandising, and customer service',
                'icon' => 'shopping-cart',
                'is_active' => true,
            ],
            [
                'name' => 'Media & Communication',
                'description' => 'Jobs related to journalism, media production, and communications',
                'icon' => 'video',
                'is_active' => true,
            ],
            [
                'name' => 'Legal',
                'description' => 'Jobs related to legal services, law, and compliance',
                'icon' => 'scale-balanced',
                'is_active' => true,
            ],
            [
                'name' => 'Engineering',
                'description' => 'Jobs related to various engineering disciplines and technical design',
                'icon' => 'gear',
                'is_active' => true,
            ],
            [
                'name' => 'Others',
                'description' => 'Other job categories not listed above',
                'icon' => 'briefcase',
                'is_active' => true,
            ],
        ];

        foreach ($categories as $category) {
            // Generate slug from name
            $slug = Str::slug($category['name']);

            // Check if category already exists
            if (!JobCategory::query()->where('slug', $slug)->exists()) {
                JobCategory::query()->create([
                    'name' => $category['name'],
                    'slug' => $slug,
                    'description' => $category['description'],
                    'icon' => $category['icon'],
                    'is_active' => $category['is_active'],
                ]);
            }
        }
    }
}
