<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    protected $fillable = ['sale_id', 'payment_method', 'amount'];

    protected $casts = ['amount' => 'decimal:2'];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
