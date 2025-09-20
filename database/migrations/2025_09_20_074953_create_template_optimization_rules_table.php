<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('template_optimization_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('environment', ['all', 'local', 'production', 'staging'])->default('all');
            $table->string('template_pattern')->nullable();
            $table->string('history_from', 20)->nullable();
            $table->string('history_to', 20)->nullable();
            $table->string('trends_from', 20)->nullable();
            $table->string('trends_to', 20)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index(['environment', 'is_active']);
            $table->index('template_pattern');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_optimization_rules');
    }
};
