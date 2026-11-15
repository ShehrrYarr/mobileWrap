<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw materials are stocked per cm², so their unit costs are fractions of a rupee
 * (e.g. Rs. 0.2083/cm²). Four decimals keep weighted-average costing accurate.
 */
return new class extends Migration
{
    protected array $columns = [
        'product_variants' => ['cost_price', 0],
        'inventory_stocks' => ['average_cost', 0],
        'stock_movements' => ['unit_cost', 0],
        'purchase_order_items' => ['unit_cost', null],
        'order_items' => ['unit_cost', 0],
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => [$column, $default]) {
            Schema::table($table, function (Blueprint $t) use ($column, $default) {
                $col = $t->decimal($column, 14, 4);
                $default === null ? $col->change() : $col->default($default)->change();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => [$column, $default]) {
            Schema::table($table, function (Blueprint $t) use ($column, $default) {
                $col = $t->decimal($column, 12, 2);
                $default === null ? $col->change() : $col->default($default)->change();
            });
        }
    }
};
