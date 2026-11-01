<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_veg' => 'boolean',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'has_variants' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function variants()
    {
        return $this->hasMany(MenuItemVariant::class, 'menu_item_id')->orderBy('sort_order')->orderBy('price');
    }
}
