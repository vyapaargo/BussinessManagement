<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Plan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'duration_value' => 'integer',
        'price' => 'decimal:2',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function features()
    {
        return $this->hasMany(PlanFeature::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(OrganizationSubscription::class);
    }

    /**
     * Calculate subscription expiry date from a starting date using configured duration.
     * Accurately handles leap years and month-end date transitions.
     */
    public function calculateEndsAt(?Carbon $startsAt = null): Carbon
    {
        $start = $startsAt ? $startsAt->copy() : Carbon::today();
        $unit = strtolower(trim($this->duration_unit ?: 'days'));
        $value = (int) ($this->duration_value ?: 30);

        if ($unit === 'years' || $unit === 'year') {
            return $start->addYearsNoOverflow($value);
        } elseif ($unit === 'months' || $unit === 'month') {
            return $start->addMonthsNoOverflow($value);
        } else {
            return $start->addDays($value);
        }
    }

    /**
     * Human-readable duration format (e.g., "30 Days", "3 Months", "1 Year").
     */
    public function getFormattedDurationAttribute(): string
    {
        $value = (int) ($this->duration_value ?: 30);
        $unit = strtolower(trim($this->duration_unit ?: 'days'));

        if ($unit === 'years' || $unit === 'year') {
            return $value === 1 ? '1 Year' : "{$value} Years";
        } elseif ($unit === 'months' || $unit === 'month') {
            return $value === 1 ? '1 Month' : "{$value} Months";
        } else {
            return $value === 1 ? '1 Day' : "{$value} Days";
        }
    }

    /**
     * Effective price for the configured plan duration.
     */
    public function getEffectivePriceAttribute(): float
    {
        if ($this->price > 0) {
            return (float) $this->price;
        }
        if ($this->price_monthly > 0) {
            return (float) $this->price_monthly;
        }
        return 0.00;
    }
}
