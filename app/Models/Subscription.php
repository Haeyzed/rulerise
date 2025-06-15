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

/**
 * @property int $id
 * @property int $employer_id
 * @property int $plan_id
 * @property string $subscription_id
 * @property string $payment_provider
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property \Carbon\Carbon $start_date
 * @property \Carbon\Carbon|null $end_date
 * @property \Carbon\Carbon|null $next_billing_date
 * @property \Carbon\Carbon|null $trial_start_date
 * @property \Carbon\Carbon|null $trial_end_date
 * @property bool $is_trial
 * @property bool $trial_ended
 * @property \Carbon\Carbon|null $canceled_at
 * @property array|null $metadata
 * @property bool $is_active
 */
class Subscription extends Model
{
    use HasFactory;

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
    ];

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

    public function isActive(): bool
    {
        return $this->is_active &&
            $this->status === 'active' &&
            ($this->end_date === null || $this->end_date->isFuture());
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isInTrial(): bool
    {
        return $this->is_trial &&
            !$this->trial_ended &&
            $this->trial_end_date &&
            $this->trial_end_date->isFuture();
    }

    public function endTrial(): void
    {
        $this->update([
            'is_trial' => false,
            'trial_ended' => true,
        ]);
    }

    public function cancel(): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'is_active' => false,
        ]);

        // Send cancellation notification
        $this->employer->user->notify(new SubscriptionCancelled($this));
    }

    public function suspend(): void
    {
        $this->update([
            'status' => 'suspended',
            'is_active' => false,
        ]);

        // Send suspension notification
        $this->employer->user->notify(new SubscriptionSuspended($this));
    }

    public function resume(): void
    {
        $this->update([
            'status' => 'active',
            'is_active' => true,
        ]);

        // Send resumption notification
        $this->employer->user->notify(new SubscriptionResumed($this));
    }

    public function activate(): void
    {
        $this->update([
            'status' => 'active',
            'is_active' => true,
        ]);

        // Send activation notification
        $this->employer->user->notify(new SubscriptionActivated($this));
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }

    public function scopeSuspended($query)
    {
        return $query->where('status', 'suspended');
    }

    public function scopeInTrial($query)
    {
        return $query->where('is_trial', true)
            ->where('trial_ended', false)
            ->whereNotNull('trial_end_date')
            ->where('trial_end_date', '>=', now());
    }
}
