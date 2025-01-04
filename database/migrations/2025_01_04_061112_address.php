<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('address', function (Blueprint $table) {
            $table->id('address_id'); // Auto-incrementing primary key for the address
            $table->uuid('business_id'); // Foreign key to the business
            $table->string('street');
            $table->string('city');
            $table->string('zip');
            $table->timestamps();

            // Foreign key relationship
            $table->foreign('business_id')->references('business_id')->on('businesses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('address');
    }
};
