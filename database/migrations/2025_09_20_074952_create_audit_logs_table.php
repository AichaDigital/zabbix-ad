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
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('zabbix_connection_id')->nullable()->constrained()->onDelete('set null');
            $table->string('action', 100);
            $table->string('resource_type', 50);
            $table->string('resource_id', 100)->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->enum('status', ['success', 'failed', 'partial']);
            $table->text('error_message')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at');

            // Indexes
            $table->index(['user_id', 'created_at']);
            $table->index(['zabbix_connection_id', 'action']);
            $table->index(['resource_type', 'resource_id']);
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
