<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashClosureAudit extends Model
{
    protected $fillable = ['cash_session_id', 'user_id', 'action', 'reason', 'metadata'];

    protected $casts = ['metadata' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
