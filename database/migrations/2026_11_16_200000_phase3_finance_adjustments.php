<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Phase 3 (vendors, purchases, ledgers): vendor bill number, due date, purchase returns. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->string('bill_number', 60)->nullable()->after('number'); // vendor's own invoice number
            $table->date('due_date')->nullable()->after('received_date');
            $table->decimal('received_total', 14, 2)->default(0)->after('total'); // payable booked so far (received goods)
            $table->decimal('returned_total', 14, 2)->default(0)->after('paid_amount');
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->decimal('returned_quantity', 14, 3)->default(0)->after('received_quantity');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropColumn('returned_quantity');
        });

        Schema::table('purchase_orders', function (Blueprint $table) {
            $table->dropColumn(['bill_number', 'due_date', 'received_total', 'returned_total']);
        });
    }
};
