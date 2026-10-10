<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->date('business_date')->unique();
            $table->decimal('opening_balance', 14, 2);
            $table->foreignId('opened_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('counted_cash', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('cash_difference', 14, 2)->nullable();
            $table->json('report_snapshot')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->enum('status', ['open', 'closed'])->default('open');
            $table->timestamps();
        });

        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->date('movement_date');
            $table->enum('type', ['income', 'expense']);
            $table->enum('payment_method', ['efectivo', 'transferencia', 'tarjeta', 'otro'])->default('efectivo');
            $table->decimal('amount', 14, 2);
            $table->string('concept');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index(['movement_date', 'type']);
        });

        Schema::create('cash_closure_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('action');
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('monthly_cash_closures', function (Blueprint $table) {
            $table->id();
            $table->date('period_start');
            $table->date('period_end');
            $table->json('report_snapshot');
            $table->foreignId('closed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
            $table->unique(['period_start', 'period_end']);
        });

        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['efectivo', 'transferencia', 'tarjeta', 'otro']);
            $table->decimal('amount', 14, 2);
            $table->timestamps();
            $table->index(['payment_method', 'sale_id']);
        });

        Schema::table('sale_items', function (Blueprint $table) {
            $table->decimal('cost_price', 14, 2)->nullable()->after('unit_price');
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', fn (Blueprint $table) => $table->dropColumn('cost_price'));
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('monthly_cash_closures');
        Schema::dropIfExists('cash_closure_audits');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
    }
};
