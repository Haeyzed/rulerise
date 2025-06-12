<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $employer_id
 * @property int $plan_id
 * @property int|null $subscription_id
 * @property string $payment_id
 * @property string $payment_provider
 * @property string $payment_type
 * @property string $status
 * @property float $amount
 * @property string $currency
 * @property string|null $payment_method
 * @property array|null $provider_response
 * @property string|null $invoice_url
 * @property \Carbon\Carbon|null $paid_at
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'plan_id',
        'subscription_id',
        'payment_id',
        'payment_provider',
        'payment_type',
        'status',
        'amount',
        'currency',
        'payment_method',
        'provider_response',
        'invoice_url',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'provider_response' => 'array',
        'paid_at' => 'datetime',
    ];

    public function employer(): BelongsTo
    {
        return $this->belongsTo(Employer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeByProvider($query, string $provider)
    {
        return $query->where('payment_provider', $provider);
    }
}
