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
        Schema::create('zabbix_connections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('url', 500);
            $table->text('encrypted_token');
            $table->enum('environment', ['local', 'production', 'staging'])->default('production');
            $table->boolean('is_active')->default(true);
            $table->integer('max_requests_per_minute')->default(60);
            $table->integer('timeout_seconds')->default(30);
            $table->timestamp('last_connection_test')->nullable();
            $table->enum('connection_status', ['active', 'inactive', 'error'])->default('active');
            $table->timestamps();

            // Indexes
            $table->index(['environment', 'is_active']);
            $table->index('connection_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zabbix_connections');
    }
};
