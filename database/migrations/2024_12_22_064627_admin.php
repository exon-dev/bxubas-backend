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
        Schema::create('admins', function (Blueprint $table) {
            $table->char('admin_id', 36)->primary(); // Primary Key
            $table->string('email')->unique(); // Unique email
            $table->string('first_name'); // First Name
            $table->string('last_name'); // Last Name
            $table->string('password'); // Password
            $table->string('image_url')->nullable();
            $table->timestamps(); // Adds created_at and updated_at fields
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admins'); // Drops the table if it exists
    }
};
