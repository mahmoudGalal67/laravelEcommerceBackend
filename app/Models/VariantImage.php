<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VariantImage extends Model
{
    protected $fillable = [
        'variant_id',
        'file_path',
        'sort_order',
        'is_main',
    ];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}
