<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laser cutter job queue (tracker only — the machine software holds templates).
        Schema::create('laser_cutter_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('number', 30)->unique();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('device_model_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_variant_id')->constrained('product_variants')->restrictOnDelete();
            $table->enum('side', ['front', 'back', 'full'])->default('front');
            $table->decimal('cut_width_mm', 8, 2);
            $table->decimal('cut_height_mm', 8, 2);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->decimal('area_cm2', 12, 2);        // per piece
            $table->decimal('wastage_percent', 5, 2)->default(0);
            $table->decimal('total_area_cm2', 12, 2);  // deducted from roll
            $table->decimal('service_fee', 12, 2)->default(0);
            $table->enum('status', ['queued', 'cutting', 'completed', 'failed', 'cancelled'])->default('queued');
            $table->unsignedTinyInteger('priority')->default(0);
            $table->foreignId('operator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('material_deducted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laser_cutter_jobs');
    }
};
