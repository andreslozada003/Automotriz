<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyCashClosure extends Model
{
    protected $fillable = ['period_start', 'period_end', 'report_snapshot', 'closed_by', 'closed_at'];

    protected $casts = ['period_start' => 'date', 'period_end' => 'date', 'report_snapshot' => 'array', 'closed_at' => 'datetime'];

    public function closer()
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
