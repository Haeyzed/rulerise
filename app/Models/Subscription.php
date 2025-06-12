<?php

namespace App\Models;

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
        'cv_downloads_left',
        'canceled_at',
        'metadata',
        'is_active',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'next_billing_date' => 'datetime',
        'canceled_at' => 'datetime',
        'metadata' => 'array',
        'is_active' => 'boolean',
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

    public function cancel(): void
    {
        $this->update([
            'status' => 'canceled',
            'canceled_at' => now(),
            'is_active' => false,
        ]);
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
}
