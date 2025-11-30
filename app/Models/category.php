<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
    ];
    public function products()
    {
        return $this->belongsToMany(Product::class);
    }
}
