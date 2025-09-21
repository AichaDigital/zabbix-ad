<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackgroundJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_type',
        'zabbix_connection_id',
        'parameters',
        'status',
        'progress_percentage',
        'started_at',
        'completed_at',
        'error_message',
        'result_data',
    ];

    protected $casts = [
        'parameters' => 'array',
        'progress_percentage' => 'integer',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'result_data' => 'array',
    ];

    /**
     * Get the Zabbix connection
     *
     * @return BelongsTo<ZabbixConnection, $this>
     */
    public function zabbixConnection(): BelongsTo
    {
        return $this->belongsTo(ZabbixConnection::class);
    }

    /**
     * Scope for pending jobs
     */
    /**
     * @param  Builder<BackgroundJob>  $query
     * @return Builder<BackgroundJob>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for running jobs
     */
    /**
     * @param  Builder<BackgroundJob>  $query
     * @return Builder<BackgroundJob>
     */
    public function scopeRunning(Builder $query): Builder
    {
        return $query->where('status', 'running');
    }

    /**
     * Scope for completed jobs
     */
    /**
     * @param  Builder<BackgroundJob>  $query
     * @return Builder<BackgroundJob>
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed jobs
     */
    /**
     * @param  Builder<BackgroundJob>  $query
     * @return Builder<BackgroundJob>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for jobs by type
     */
    /**
     * @param  Builder<BackgroundJob>  $query
     * @return Builder<BackgroundJob>
     */
    public function scopeByType(Builder $query, string $jobType): Builder
    {
        return $query->where('job_type', $jobType);
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        $map = [
            'pending' => 'secondary',
            'running' => 'primary',
            'completed' => 'success',
            'failed' => 'danger',
            'cancelled' => 'warning',
        ];

        $status = (string) $this->status;

        return $map[$status];
    }

    /**
     * Get duration in human readable format
     */
    public function getDurationAttribute(): ?string
    {
        if (! $this->started_at) {
            return null;
        }

        $end = $this->completed_at ?? now();
        $duration = $this->started_at->diffInSeconds($end);

        if ($duration < 60) {
            return $duration.'s';
        }

        if ($duration < 3600) {
            return round($duration / 60, 1).'m';
        }

        return round($duration / 3600, 1).'h';
    }

    /**
     * Mark job as started
     */
    public function markAsStarted(): void
    {
        $this->update([
            'status' => 'running',
            'started_at' => now(),
            'progress_percentage' => 0,
        ]);
    }

    /**
     * Update job progress
     *
     * @param  array<string, mixed>|null  $resultData
     */
    public function updateProgress(int $percentage, ?array $resultData = null): void
    {
        $this->update([
            'progress_percentage' => min(100, max(0, $percentage)),
            'result_data' => $resultData,
        ]);
    }

    /**
     * Mark job as completed
     *
     * @param  array<string, mixed>|null  $resultData
     */
    public function markAsCompleted(?array $resultData = null): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'progress_percentage' => 100,
            'result_data' => $resultData,
        ]);
    }

    /**
     * Mark job as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark job as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if job is finished
     */
    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled']);
    }

    /**
     * Check if job is running
     */
    public function isRunning(): bool
    {
        return $this->status === 'running';
    }

    /**
     * Get job statistics
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        return [
            'status' => $this->status,
            'progress_percentage' => $this->progress_percentage,
            'duration' => $this->duration,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'is_finished' => $this->isFinished(),
            'is_running' => $this->isRunning(),
        ];
    }
}
