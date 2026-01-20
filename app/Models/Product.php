<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'seller_id',
        'name',
        'slug',
        'description',
        'base_price',
        'is_active',
        'is_featured',
        'base_images',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'base_images' => 'array',
    ];

    // Relationships
    public function seller()
    {
        return $this->belongsTo(Seller::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }

    public function wishlistItems()
    {
        return $this->hasMany(WishlistItem::class);
    }
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }
}
