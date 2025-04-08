<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

/**
 * User model representing all users in the system
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $email
 * @property string $password
 * @property string|null $phone
 * @property string|null $profile_picture
 * @property string $user_type
 * @property bool $is_active
 * @property bool $is_shadow_banned
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 *
 * @property-read Candidate|null $candidate
 * @property-read Employer|null $employer
 */
class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable, SoftDeletes, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'password',
        'phone',
        'profile_picture',
        'user_type',
        'is_active',
        'is_shadow_banned',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_shadow_banned' => 'boolean',
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims(): array
    {
        return [];
    }

    /**
     * Get the candidate associated with the user.
     */
    public function candidate(): HasOne
    {
        return $this->hasOne(Candidate::class);
    }

    /**
     * Get the employer associated with the user.
     */
    public function employer(): HasOne
    {
        return $this->hasOne(Employer::class);
    }

    /**
     * Check if user is a candidate
     *
     * @return bool
     */
    public function isCandidate(): bool
    {
        return $this->user_type === 'candidate';
    }

    /**
     * Check if user is an employer
     *
     * @return bool
     */
    public function isEmployer(): bool
    {
        return $this->user_type === 'employer';
    }

    /**
     * Check if user is an admin
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        return $this->user_type === 'admin';
    }
}
