<?php

namespace App\Models;

use App\Models\Concerns\NormalizesCatalogFields;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use NormalizesCatalogFields;

    protected $fillable = [
        'name',
        'status',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
