<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per variant (single shop). Raw materials hold cm² in `quantity`.
        Schema::create('inventory_stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->decimal('reserved_quantity', 14, 3)->default(0);
            $table->decimal('average_cost', 12, 2)->default(0);
            $table->timestamps();
        });

        // Stock ledger — every in/out with running balance.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening, purchase, purchase_return, sale, sale_return, online_order, online_cancel, adjustment_in, adjustment_out, laser_usage, wastage
            $table->decimal('quantity', 14, 3); // signed: + in, - out
            $table->decimal('unit_cost', 12, 2)->default(0);
            $table->decimal('balance_after', 14, 3);
            $table->nullableMorphs('reference'); // order, purchase order, laser job…
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['product_variant_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('inventory_stocks');
    }
};
