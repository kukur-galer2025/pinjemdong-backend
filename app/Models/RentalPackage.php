<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class RentalPackage extends Model
{
    protected $fillable = [
        'name', 'slug', 'description', 'price_per_day',
        'original_price_per_day', 'min_dp_percentage', 'image', 'is_active',
    ];

    protected $casts = [
        'price_per_day' => 'decimal:2',
        'original_price_per_day' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(RentalPackageItem::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'rental_package_items')->withPivot('quantity');
    }

    public function getDiscountPercentageAttribute(): int
    {
        if ($this->original_price_per_day <= 0) return 0;
        return round((1 - $this->price_per_day / $this->original_price_per_day) * 100);
    }
}
