<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2 (POS, laser queue, returns): exchange credit as a payment method,
 * cash tendered per payment, and re-cut tracking for laser jobs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_payments MODIFY method ENUM('cash','bank_transfer','customer_credit','cod','exchange_credit') NOT NULL");
            DB::statement("ALTER TABLE order_returns MODIFY refund_method ENUM('cash','bank_transfer','customer_credit','exchange','none') NOT NULL DEFAULT 'cash'");
        }

        Schema::table('order_payments', function (Blueprint $table) {
            $table->decimal('tendered', 14, 2)->nullable()->after('amount'); // cash handed over (change = tendered - amount)
        });

        Schema::table('order_returns', function (Blueprint $table) {
            $table->foreignId('bank_account_id')->nullable()->after('refund_amount')->constrained()->nullOnDelete();
            $table->foreignId('financial_transaction_id')->nullable()->after('bank_account_id')->constrained()->nullOnDelete();
        });

        Schema::table('laser_cutter_jobs', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('order_item_id')->constrained('laser_cutter_jobs')->nullOnDelete();
            $table->unsignedTinyInteger('attempt')->default(1)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('laser_cutter_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn('attempt');
        });

        Schema::table('order_returns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('financial_transaction_id');
            $table->dropConstrainedForeignId('bank_account_id');
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('tendered');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE order_returns MODIFY refund_method ENUM('cash','bank_transfer','customer_credit','none') NOT NULL DEFAULT 'cash'");
            DB::statement("ALTER TABLE order_payments MODIFY method ENUM('cash','bank_transfer','customer_credit','cod') NOT NULL");
        }
    }
};
