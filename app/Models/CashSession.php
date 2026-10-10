<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashSession extends Model
{
    protected $fillable = ['business_date', 'opening_balance', 'opened_by', 'opened_at', 'counted_cash', 'expected_cash', 'cash_difference', 'report_snapshot', 'closed_by', 'closed_at', 'status'];

    protected $casts = ['business_date' => 'date', 'opening_balance' => 'decimal:2', 'counted_cash' => 'decimal:2', 'expected_cash' => 'decimal:2', 'cash_difference' => 'decimal:2', 'report_snapshot' => 'array', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function opener()
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function audits()
    {
        return $this->hasMany(CashClosureAudit::class);
    }
}
