<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Saved expenses can be corrected; remember who last changed one and when. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('edited_by')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('edited_at')->nullable()->after('edited_by');
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropIndex(['date']);
            $table->dropConstrainedForeignId('edited_by');
            $table->dropColumn('edited_at');
        });
    }
};
