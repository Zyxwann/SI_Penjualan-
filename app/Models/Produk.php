<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Produk extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'thumnail',
        'about',
        'size',
        'photo',
        'price',
        'stock',
        'is_populer',
        'category_id',
        'brand_id',
    ];

    /**
     * Auto-generate slug dari name
     */
    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $value;
        $this->attributes['slug'] = Str::slug($value);
    }

    /**
     * Relasi ke Brand
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Relasi ke Category
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Relasi ke tabel produk_photos
     */
    public function photos(): HasMany
    {
        return $this->hasMany(ProdukPhoto::class);
    }

    /**
     * Relasi ke tabel produk_sizes
     */
    public function sizes(): HasMany
    {
        return $this->hasMany(ProdukSize::class);
    }
}
