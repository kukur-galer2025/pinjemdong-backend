<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Rental extends Model
{
    protected $fillable = [
        'invoice_number', 'user_id', 'start_date', 'end_date',
        'pickup_time', 'return_time',
        'actual_return_date', 'total_days', 'subtotal', 'delivery_cost',
        'late_fee_total', 'total_amount', 'dp_amount', 'remaining_amount',
        'delivery_method', 'delivery_address',
        'delivery_latitude', 'delivery_longitude', 'delivery_distance_km',
        'status', 'return_condition', 'return_notes', 'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_return_date' => 'date',
        'subtotal' => 'decimal:2',
        'delivery_cost' => 'decimal:2',
        'late_fee_total' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'dp_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'delivery_latitude' => 'double',
        'delivery_longitude' => 'double',
        'delivery_distance_km' => 'decimal:2',
    ];

    protected $appends = ['is_overdue', 'overdue_days', 'late_days', 'penalty_amount', 'final_total_amount', 'final_remaining_amount'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RentalItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status !== 'rented') return false;
        $returnTimeStr = $this->return_time ?? '23:59:59';
        $returnDateTime = Carbon::parse($this->end_date->format('Y-m-d') . ' ' . $returnTimeStr);
        return Carbon::now()->greaterThan($returnDateTime);
    }

    public function getLateDaysAttribute(): int
    {
        if ($this->status !== 'rented') {
            if ($this->late_fee_total > 0 && $this->total_days > 0) {
                $dailyRate = $this->subtotal / $this->total_days;
                return (int) round($this->late_fee_total / max(1, $dailyRate));
            }
            return 0;
        }
        return $this->overdue_days;
    }

    public function getPenaltyAmountAttribute(): float
    {
        if ($this->late_fee_total > 0) {
            return (float) $this->late_fee_total;
        }
        if ($this->status === 'rented' && $this->is_overdue && $this->total_days > 0) {
            $dailyRate = $this->subtotal / $this->total_days;
            return (float) ($this->overdue_days * $dailyRate);
        }
        return 0;
    }

    public function getFinalTotalAmountAttribute(): float
    {
        if ($this->late_fee_total > 0) {
            return (float) $this->total_amount;
        }
        return (float) ($this->total_amount + $this->penalty_amount);
    }

    public function getFinalRemainingAmountAttribute(): float
    {
        if ($this->late_fee_total > 0) {
            return (float) $this->remaining_amount;
        }
        return (float) ($this->remaining_amount + $this->penalty_amount);
    }

    public function getOverdueDaysAttribute(): int
    {
        if (!$this->is_overdue) return 0;
        $returnTimeStr = $this->return_time ?? '23:59:59';
        $returnDateTime = Carbon::parse($this->end_date->format('Y-m-d') . ' ' . $returnTimeStr);
        
        $minutesLate = $returnDateTime->diffInMinutes(Carbon::now());
        return max(1, (int) ceil($minutesLate / (24 * 60)));
    }

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'PJD';
        $date = now()->format('Ymd');
        $last = static::whereDate('created_at', today())->count() + 1;
        return "{$prefix}-{$date}-" . str_pad($last, 4, '0', STR_PAD_LEFT);
    }
}
