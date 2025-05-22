<?php

namespace App\Services;

use App\Models\Candidate;
use App\Models\Employer;
use App\Models\GeneralSetting;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\JobCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\WebsiteCustomization;
use App\Services\Storage\StorageService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Service class for admin related operations
 */
class AdminService
{
    /**
     * @var StorageService
     */
    protected StorageService $storageService;

    /**
     * BlogPostService constructor.
     *
     * @param StorageService $storageService
     */
    public function __construct(StorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    /**
     * Get dashboard metrics
     *
     * @return array
     */
    public function getDashboardMetrics(): array
    {
        $totalUsers = User::query()->count();
        $totalCandidates = Candidate::query()->count();
        $totalEmployers = Employer::query()->count();
        $totalJobs = Job::query()->count();
        $totalApplications = JobApplication::query()->count();
        $totalRevenue = Subscription::query()->sum('amount_paid');

        $recentUsers = User::query()->latest()->limit(5)->get();
        $recentJobs = Job::with('employer')->latest()->limit(5)->get();

        return [
            'totalUsers' => $totalUsers,
            'totalCandidates' => $totalCandidates,
            'totalEmployers' => $totalEmployers,
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'totalRevenue' => $totalRevenue,
            'recentUsers' => $recentUsers,
            'recentJobs' => $recentJobs,
        ];
    }

    /**
     * Moderate user account status
     *
     * @param User $user
     * @param bool $isActive
     * @return User
     */
    public function moderateAccountStatus(User $user, bool $isActive): User
    {
        $user->is_active = $isActive;
        $user->save();

        return $user;
    }

    /**
     * Set user shadow ban status
     *
     * @param User $user
     * @param bool $isShadowBanned
     * @return User
     */
    public function setShadowBan(User $user, bool $isShadowBanned): User
    {
        $user->is_shadow_banned = $isShadowBanned;
        $user->save();

        return $user;
    }

    /**
     * Create a new subscription plan.
     *
     * @param array $data
     * @return SubscriptionPlan
     */
    public function createSubscriptionPlan(array $data): SubscriptionPlan
    {
        return SubscriptionPlan::create($data);
    }

    /**
     * Update an existing subscription plan.
     *
     * @param SubscriptionPlan $subscriptionPlan
     * @param array $data
     * @return SubscriptionPlan
     */
    public function updateSubscriptionPlan(SubscriptionPlan $subscriptionPlan, array $data): SubscriptionPlan
    {
        $subscriptionPlan->update($data);
        return $subscriptionPlan;
    }


    /**
     * Set subscription plan active status
     *
     * @param SubscriptionPlan $plan
     * @param bool $isActive
     * @return SubscriptionPlan
     */
    public function setSubscriptionPlanStatus(SubscriptionPlan $plan, bool $isActive): SubscriptionPlan
    {
        $plan->is_active = $isActive;
        $plan->save();

        return $plan;
    }

    /**
     * Create or update job category
     *
     * @param array $data
     * @param JobCategory|null $jobCategory
     * @return JobCategory
     */
    public function saveJobCategory(array $data, ?JobCategory $jobCategory = null): JobCategory
    {
        if ($jobCategory) {
            $jobCategory->update($data);
        } else {
            $jobCategory = JobCategory::query()->create($data);
        }

        return $jobCategory;
    }

    /**
     * Set job category active status
     *
     * @param JobCategory $category
     * @param bool $isActive
     * @return JobCategory
     */
    public function setJobCategoryStatus(JobCategory $category, bool $isActive): JobCategory
    {
        $category->is_active = $isActive;
        $category->save();

        return $category;
    }

    /**
     * Save website customization
     *
     * @param string $type
     * @param string $key
     * @param mixed $value
     * @param bool $isActive
     * @return WebsiteCustomization
     */
    public function saveWebsiteCustomization(string $type, string $key, mixed $value, bool $isActive = true): WebsiteCustomization
    {
        return WebsiteCustomization::query()->updateOrCreate(
            ['type' => $type, 'key' => $key],
            ['value' => $value, 'is_active' => $isActive]
        );
    }

    /**
     * Upload website image
     *
     * @param string $type
     * @param string $key
     * @param UploadedFile $file
     * @return WebsiteCustomization
     */
    public function uploadWebsiteImage(string $type, string $key, UploadedFile $file): WebsiteCustomization
    {
        // Check if image already exists
        $customization = WebsiteCustomization::query()
            ->where('type', $type)
            ->where('key', $key)
            ->first();

        // Delete old image if exists
        if ($customization && $customization->value) {
            $this->storageService->delete($customization->value);
        }

        // Upload new image
        $uploadedPath = $this->upload(
            $file,
            config('filestorage.paths.website_customization'),
        );

        // Save customization
        return $this->saveWebsiteCustomization($type, $key, $uploadedPath);
    }

    /**
     * Save general setting
     *
     * @param string $key
     * @param mixed $value
     * @return GeneralSetting
     */
    public function saveGeneralSetting(string $key, mixed $value): GeneralSetting
    {
        return GeneralSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    /**
     * Create or update role
     *
     * @param string $name
     * @param array $permissions
     * @return Role
     */
    public function saveRole(string $name, array $permissions): Role
    {
        $role = Role::findOrCreate($name);
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Delete role
     *
     * @param string $name
     * @return bool
     */
    public function deleteRole(string $name): bool
    {
        $role = Role::findByName($name);
        return $role->delete();
    }

    /**
     * Create admin user
     *
     * @param array $data
     * @param array $roles
     * @return User
     */
    public function createAdminUser(array $data, array $roles): User
    {
        // Create user
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
            'user_type' => 'admin',
        ]);

        // Assign roles
        $user->assignRole($roles);

        return $user;
    }

    /**
     * Update admin user
     *
     * @param User $user
     * @param array $data
     * @param array $roles
     * @return User
     */
    public function updateAdminUser(User $user, array $data, array $roles): User
    {
        // Update user data
        $user->name = $data['name'] ?? $user->name;
        $user->email = $data['email'] ?? $user->email;

        if (isset($data['password'])) {
            $user->password = bcrypt($data['password']);
        }

        $user->save();

        // Sync roles
        $user->syncRoles($roles);

        return $user;
    }

    /**
     * Delete admin user
     *
     * @param User $user
     * @return bool
     */
    public function deleteAdminUser(User $user): bool
    {
        return $user->delete();
    }

    /**
     * Get all permissions
     *
     * @return Collection
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
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
    private function upload(UploadedFile $image, string $path, string &$fileName = null, array $options = []): string
    {
        $fileName = time() . '-' . Str::slug(pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $image->getClientOriginalExtension();

        return $this->storageService->upload($image, $path, $fileName, $options);
    }

}
