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
        Schema::create('background_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('job_type', 100);
            $table->foreignId('zabbix_connection_id')->nullable()->constrained()->onDelete('set null');
            $table->json('parameters')->nullable();
            $table->enum('status', ['pending', 'running', 'completed', 'failed', 'cancelled'])->default('pending');
            $table->integer('progress_percentage')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result_data')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['job_type', 'status']);
            $table->index('zabbix_connection_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('background_jobs');
    }
};
