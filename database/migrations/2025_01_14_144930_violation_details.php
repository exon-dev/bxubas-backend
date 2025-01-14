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
        Schema::create('violation_details', function (Blueprint $table) {
            $table->engine = 'InnoDB';

            $table->id(); // Primary key
            $table->char('violation_id', 36); // Foreign key reference to violations table
            $table->string('nature_of_violation'); // Add this column for <nature_of_violation></nature_of_violation>
            $table->timestamps();

            // Foreign key constraint for violation_id referencing violations table
            $table->index('violation_id'); // Create index for violation_id
            $table->foreign('violation_id')
                ->references('violation_id')
                ->on('violations')
                ->onDelete('cascade'); // Cascade on delete from violations table
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the foreign key and the violation_details table
        Schema::table('violation_details', function (Blueprint $table) {
            $table->dropForeign(['violation_id']);
        });

        Schema::dropIfExists('violation_details');
    }
};
