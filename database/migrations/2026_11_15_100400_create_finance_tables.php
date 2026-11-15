<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');
            $table->string('account_title');
            $table->string('account_number', 60);
            $table->string('iban', 60)->nullable();
            $table->string('branch')->nullable();
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('show_on_checkout')->default(false); // listed for manual bank transfer
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Cash In Hand — single shop has one drawer, table allows more later.
        Schema::create('cash_drawers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('opening_balance', 14, 2)->default(0);
            $table->decimal('current_balance', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Money ledger for bank accounts and cash drawers.
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('account'); // BankAccount | CashDrawer
            $table->string('type', 30); // opening, sale_receipt, customer_payment, vendor_payment, purchase_payment, expense, refund, transfer_in, transfer_out, deposit, withdrawal, adjustment
            $table->decimal('debit', 14, 2)->default(0);  // money in
            $table->decimal('credit', 14, 2)->default(0); // money out
            $table->decimal('balance_after', 14, 2);
            $table->nullableMorphs('reference'); // Order, OrderPayment, PurchaseOrder, Expense, OrderReturn
            $table->nullableMorphs('party');     // Customer | Vendor
            $table->uuid('transfer_group')->nullable();
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['account_type', 'account_id', 'date']);
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->morphs('account'); // paid from bank or cash
            $table->decimal('amount', 14, 2);
            $table->date('date');
            $table->string('note')->nullable();
            $table->string('attachment')->nullable();
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vendor_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening, purchase, payment, return, adjustment
            $table->decimal('debit', 14, 2)->default(0);  // reduces payable (payment, return)
            $table->decimal('credit', 14, 2)->default(0); // increases payable (purchase)
            $table->decimal('balance_after', 14, 2);
            $table->nullableMorphs('reference');
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['vendor_id', 'date']);
        });

        Schema::create('customer_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30); // opening, sale, payment, return, refund, adjustment
            $table->decimal('debit', 14, 2)->default(0);  // increases receivable (credit sale)
            $table->decimal('credit', 14, 2)->default(0); // reduces receivable (payment, return)
            $table->decimal('balance_after', 14, 2);
            $table->nullableMorphs('reference');
            $table->foreignId('financial_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date');
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_transactions');
        Schema::dropIfExists('vendor_transactions');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('cash_drawers');
        Schema::dropIfExists('bank_accounts');
    }
};
