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
        Schema::create('inspectors', function (Blueprint $table) {
            $table->bigIncrements('inspector_id'); // Primary key
            $table->string('email')->unique(); // Unique email
            $table->string('first_name'); // First Name
            $table->string('last_name'); // Last Name
            $table->string('password'); // Password
            $table->unsignedBigInteger('admin_id'); // Foreign key to the Admin table
            $table->timestamps(); // Adds created_at and updated_at columns

            // Foreign key constraint
            $table->foreign('admin_id')
                ->references('admin_id')
                ->on('admins')
                ->onDelete('cascade'); // Cascading delete
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspectors'); // Drops the table if it exists
    }
};
