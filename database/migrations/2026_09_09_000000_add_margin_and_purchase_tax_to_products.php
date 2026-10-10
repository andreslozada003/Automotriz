<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('purchase_tax_rate', 5, 2)->default(19)->after('purchase_price');
            $table->decimal('profit_margin', 5, 2)->nullable()->after('purchase_tax_rate');
        });

        DB::table('products')->update(['tax_rate' => 19, 'purchase_tax_rate' => 19]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['purchase_tax_rate', 'profit_margin']);
        });
    }
};
