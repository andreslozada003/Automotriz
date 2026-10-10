<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashMovement extends Model
{
    protected $fillable = ['movement_date', 'type', 'payment_method', 'amount', 'concept', 'notes', 'user_id'];

    protected $casts = ['movement_date' => 'date', 'amount' => 'decimal:2'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
