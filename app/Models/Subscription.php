<?php

namespace App\Models;

use App\Notifications\SubscriptionActivated;
use App\Notifications\SubscriptionCancelled;
use App\Notifications\SubscriptionResumed;
use App\Notifications\SubscriptionSuspended;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

/**
 * Subscription Model
 *
 * Manages subscription records for employers with comprehensive
 * status tracking, trial management, and notification system.
 *
 * @property int $id
 * @property int $employer_id
 * @property int $plan_id
 * @property string $subscription_id
 * @property string $payment_provider
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property Carbon|null $next_billing_date
 * @property Carbon|null $trial_start_date
 * @property Carbon|null $trial_end_date
 * @property bool $is_trial
 * @property bool $trial_ended
 * @property Carbon|null $canceled_at
 * @property array|null $metadata
 * @property bool $is_active
 * @property bool $is_suspended
 * @property int $cv_downloads_left
 */
class Subscription extends Model
{
    use HasFactory;

    /**
     * Subscription status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_PAYMENT_FAILED = 'payment_failed';

    /**
     * Payment provider constants
     */
    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_PAYPAL = 'paypal';

    protected $fillable = [
        'employer_id',
        'plan_id',
        'subscription_id',
        'payment_provider',
        'status',
        'amount',
        'currency',
        'start_date',
        'end_date',
        'next_billing_date',
        'trial_start_date',
        'trial_end_date',
        'is_trial',
        'trial_ended',
        'cv_downloads_left',
        'canceled_at',
        'metadata',
        'is_active',
        'is_suspended'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'next_billing_date' => 'date',
        'trial_start_date' => 'datetime',
        'trial_end_date' => 'datetime',
        'canceled_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'boolean',
        'is_trial' => 'boolean',
        'trial_ended' => 'boolean',
        'is_suspended' => 'boolean',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    // ========================================
    // STATUS CHECKING METHODS
    // ========================================

    public function isActive(): bool
    {
        return $this->is_active &&
            $this->status === self::STATUS_ACTIVE &&
            ($this->end_date === null || $this->end_date->isFuture());
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInTrial(): bool
    {
        return $this->is_trial &&
            !$this->trial_ended &&
            $this->trial_end_date &&
            $this->trial_end_date->isFuture();
    }

    public function hasTrialExpired(): bool
    {
        return $this->is_trial &&
            $this->trial_end_date &&
            $this->trial_end_date->isPast();
    }

    // ========================================
    // STATUS MANAGEMENT METHODS
    // ========================================

    public function activate(array $subscriptionData = []): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'is_active' => true,
            'is_suspended' => false,
            'metadata' => array_merge($this->metadata ?? [], $subscriptionData),
        ]);

        $this->employer->user->notify(new SubscriptionActivated($this));
    }

    public function cancel(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELED,
            'canceled_at' => now(),
            'is_active' => false,
        ]);

        $this->employer->user->notify(new SubscriptionCancelled($this));
    }

    public function suspend(): void
    {
        $this->update([
            'status' => self::STATUS_SUSPENDED,
            'is_active' => false,
            'is_suspended' => true,
        ]);

        $this->employer->user->notify(new SubscriptionSuspended($this));
    }

    public function resume(): void
    {
        $this->update([
            'status' => self::STATUS_ACTIVE,
            'is_active' => true,
            'is_suspended' => false,
        ]);

        $this->employer->user->notify(new SubscriptionResumed($this));
    }

    public function endTrial(): void
    {
        $this->update([
            'is_trial' => false,
            'trial_ended' => true,
        ]);
    }

    public function markPaymentFailed(): void
    {
        $this->update([
            'status' => self::STATUS_PAYMENT_FAILED,
            'is_active' => false,
        ]);
    }

    // ========================================
    // UTILITY METHODS
    // ========================================

    public function getRemainingTrialDays(): int
    {
        if (!$this->isInTrial()) {
            return 0;
        }

        return max(0, now()->diffInDays($this->trial_end_date, false));
    }

    public function getFormattedAmount(): string
    {
        return number_format($this->amount, 2) . ' ' . strtoupper($this->currency);
    }

    public function getDaysUntilNextBilling(): int
    {
        if (!$this->next_billing_date) {
            return 0;
        }

        return max(0, now()->diffInDays($this->next_billing_date, false));
    }

    // ========================================
    // QUERY SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', self::STATUS_ACTIVE)
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeCanceled($query)
    {
        return $query->where('status', self::STATUS_CANCELED);
    }

    public function scopeInTrial($query)
    {
        return $query->where('is_trial', true)
            ->where('trial_ended', false)
            ->whereNotNull('trial_end_date')
            ->where('trial_end_date', '>=', now());
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('payment_provider', $provider);
    }

    public function scopeExpiringSoon($query, int $days = 7)
    {
        return $query->whereNotNull('end_date')
            ->where('end_date', '<=', now()->addDays($days))
            ->where('end_date', '>=', now());
    }
}
