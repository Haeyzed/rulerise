<?php

namespace App\Http\Controllers\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\CreateUserRequest;
use App\Http\Requests\Employer\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\NewUserCredentials;
use App\Services\EmployerService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Controller for managing employer staff members
 */
class UsersController extends Controller implements HasMiddleware
{
    /**
     * Employer service instance
     *
     * @var EmployerService
     */
    protected EmployerService $employerService;

    /**
     * Create a new controller instance.
     *
     * @param EmployerService $employerService
     * @return void
     */
    public function __construct(EmployerService $employerService)
    {
        $this->employerService = $employerService;
    }

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(['auth:api', 'role:employer']),
        ];
    }

    /**
     * Get all staff users for the employer
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        // Get users that belong to this employer's staff
        $query = User::query()
            ->where('user_type', 'employer_staff')
            ->where('employer_id', $employer->id);

        // Apply search if provided
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Paginate results
        $perPage = $request->input('per_page', 10);
        $users = $query->paginate($perPage);

        return response()->paginatedSuccess(UserResource::collection($users), 'Staff users retrieved successfully');
    }

    /**
     * Get a specific staff user
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        // Find the staff user and ensure they belong to this employer
        $staffUser = User::query()
            ->where('id', $id)
            ->where('user_type', 'employer_staff')
            ->where('employer_id', $employer->id)
            ->firstOrFail();

        return response()->success(
            new UserResource($staffUser),
            'Staff user retrieved successfully'
        );
    }

    /**
     * Create a new staff user for the employer
     *
     * @param CreateUserRequest $request
     * @return JsonResponse
     */
    public function store(CreateUserRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            // Generate a random password
            $password = Str::password(10);

            return DB::transaction(function () use ($employer, $data, $password) {
                // Create the staff user
                $newUser = User::query()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'password' => Hash::make($password),
                    'phone' => $data['phone'] ?? null,
                    'country' => $data['country'] ?? null,
                    'state' => $data['state'] ?? null,
                    'city' => $data['city'] ?? null,
                    'user_type' => 'employer_staff',
                    'employer_id' => $employer->id, // Link to the employer
                    'is_active' => true,
                ]);

                // Assign employer_staff role
                $newUser->assignRole('employer_staff');

                // Send notification email with credentials
                $newUser->notify(new NewUserCredentials($password, $employer));

                return response()->created(
                    new UserResource($newUser),
                    'Staff user created successfully. Login credentials have been sent to their email.'
                );
            });
        } catch (Exception $e) {
            return response()->serverError('Failed to create staff user: ' . $e->getMessage());
        }
    }

    /**
     * Update a staff user
     *
     * @param UpdateUserRequest $request
     * @return JsonResponse
     */
    public function update(int $id, UpdateUserRequest $request): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;
        $data = $request->validated();

        try {
            // Find the staff user and ensure they belong to this employer
            $staffUser = User::query()
                ->where('id', $id)
                ->where('user_type', 'employer_staff')
                ->where('employer_id', $employer->id)
                ->firstOrFail();

            // Update user fields
            if (isset($data['first_name'])) {
                $staffUser->first_name = $data['first_name'];
            }

            if (isset($data['last_name'])) {
                $staffUser->last_name = $data['last_name'];
            }

            if (isset($data['email'])) {
                $staffUser->email = $data['email'];
            }

            if (isset($data['phone'])) {
                $staffUser->phone = $data['phone'];
            }

            if (isset($data['country'])) {
                $staffUser->country = $data['country'];
            }

            if (isset($data['state'])) {
                $staffUser->state = $data['state'];
            }

            if (isset($data['city'])) {
                $staffUser->city = $data['city'];
            }

            if (isset($data['is_active'])) {
                $staffUser->is_active = $data['is_active'];
            }

            // Save changes
            $staffUser->save();

            return response()->success(
                new UserResource($staffUser),
                'Staff user updated successfully'
            );
        } catch (Exception $e) {
            return response()->serverError('Failed to update staff user: ' . $e->getMessage());
        }
    }

    /**
     * Delete a staff user
     *
     * @param int $id
     * @return JsonResponse
     */
    public function delete(int $id): JsonResponse
    {
        $user = auth()->user();
        $employer = $user->employer;

        try {
            // Find the staff user and ensure they belong to this employer
            $staffUser = User::query()
                ->where('id', $id)
                ->where('user_type', 'employer_staff')
                ->where('employer_id', $employer->id)
                ->firstOrFail();

            // Delete the user
            $staffUser->delete();

            return response()->success(null, 'Staff user deleted successfully');
        } catch (Exception $e) {
            return response()->serverError('Failed to delete staff user: ' . $e->getMessage());
        }
    }
}
