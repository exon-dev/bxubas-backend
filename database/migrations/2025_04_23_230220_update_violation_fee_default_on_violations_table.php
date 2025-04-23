<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the 'violations' table to set a default value for 'violation_fee'
        Schema::table('violations', function (Blueprint $table) {
            $table->decimal('violation_fee', 10, 2)->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // If you need to rollback, just remove the default value from the column
        Schema::table('violations', function (Blueprint $table) {
            $table->decimal('violation_fee', 10, 2)->change();
        });
    }
};
