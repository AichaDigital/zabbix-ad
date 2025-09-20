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
        Schema::create('zabbix_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zabbix_connection_id')->constrained()->onDelete('cascade');
            $table->string('template_id', 50);
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->enum('template_type', ['system', 'custom', 'imported'])->default('custom');
            $table->integer('items_count')->default(0);
            $table->integer('triggers_count')->default(0);
            $table->string('history_retention', 20)->default('7d');
            $table->string('trends_retention', 20)->default('30d');
            $table->boolean('is_optimized')->default(false);
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['zabbix_connection_id', 'template_id']);
            $table->index('is_optimized');
            $table->index('template_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zabbix_templates');
    }
};
