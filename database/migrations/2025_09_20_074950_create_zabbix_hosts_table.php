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
        Schema::create('zabbix_hosts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zabbix_connection_id')->constrained()->onDelete('cascade');
            $table->string('host_id', 50);
            $table->string('host_name');
            $table->string('visible_name')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->enum('status', ['enabled', 'disabled', 'maintenance'])->default('enabled');
            $table->enum('available', ['unknown', 'available', 'unavailable'])->default('unknown');
            $table->integer('templates_count')->default(0);
            $table->integer('items_count')->default(0);
            $table->timestamp('last_check')->nullable();
            $table->timestamp('last_sync')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['zabbix_connection_id', 'host_id']);
            $table->index('status');
            $table->index('available');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zabbix_hosts');
    }
};
