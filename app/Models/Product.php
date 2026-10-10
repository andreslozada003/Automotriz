<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'supplier_id',
        'code',
        'sku',
        'name',
        'description',
        'image_path',
        'purchase_price',
        'purchase_tax_rate',
        'profit_margin',
        'sale_price',
        'tax_rate',
        'stock',
        'min_stock',
        'active',
    ];

    protected $casts = [
        'purchase_price' => 'decimal:2',
        'purchase_tax_rate' => 'decimal:2',
        'profit_margin' => 'decimal:2',
        'sale_price' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'active' => 'boolean',
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function barcodes()
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function primaryBarcode()
    {
        return $this->hasOne(ProductBarcode::class)->where('is_primary', true);
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return route('products.image', ['path' => $this->image_path]);
    }
}
