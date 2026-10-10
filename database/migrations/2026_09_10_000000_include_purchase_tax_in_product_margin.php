<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Only catalogue prices are recalculated. Past sales retain their
        // saved price and tax snapshots, so issued receipts never change.
        DB::table('products')
            ->whereNotNull('profit_margin')
            ->update([
                'sale_price' => DB::raw('ROUND(purchase_price * (1 + (purchase_tax_rate / 100)) * (1 + (profit_margin / 100)), 2)'),
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->whereNotNull('profit_margin')
            ->update([
                'sale_price' => DB::raw('ROUND(purchase_price * (1 + (profit_margin / 100)), 2)'),
            ]);
    }
};
