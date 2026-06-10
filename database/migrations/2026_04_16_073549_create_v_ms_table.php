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
        Schema::create('v_ms', function (Blueprint $table) {
            $table->id();
            $table->integer('vmid')->unique();
            $table->integer('guac_connection_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('ip_address_id')->unique()->constrained('ip_addresses')->onDelete('cascade');
            $table->integer('template_id');
            $table->enum('status', ['running', 'stopped','failed','creating'])->default('creating');
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('v_ms');
    }
};
