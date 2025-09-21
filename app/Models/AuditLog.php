<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'zabbix_connection_id',
        'action',
        'resource_type',
        'resource_id',
        'old_values',
        'new_values',
        'status',
        'error_message',
        'execution_time_ms',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'execution_time_ms' => 'integer',
    ];

    /**
     * Get the user that performed the action
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

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
     * Scope for successful actions
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('status', 'success');
    }

    /**
     * Scope for failed actions
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope for actions by type
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeByAction(Builder $query, string $action): Builder
    {
        return $query->where('action', $action);
    }

    /**
     * Scope for actions by resource type
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeByResourceType(Builder $query, string $resourceType): Builder
    {
        return $query->where('resource_type', $resourceType);
    }

    /**
     * Scope for recent actions
     *
     * @param  Builder<AuditLog>  $query
     * @return Builder<AuditLog>
     */
    public function scopeRecent(Builder $query, int $days = 7): Builder
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Get status badge color
     */
    public function getStatusColorAttribute(): string
    {
        $map = [
            'success' => 'success',
            'failed' => 'danger',
            'partial' => 'warning',
        ];

        $status = (string) $this->status;

        return $map[$status];
    }

    /**
     * Get execution time in human readable format
     */
    public function getExecutionTimeHumanAttribute(): string
    {
        if ($this->execution_time_ms < 1000) {
            return $this->execution_time_ms.'ms';
        }

        return round($this->execution_time_ms / 1000, 2).'s';
    }

    /**
     * Log a successful action
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public static function logSuccess(
        int $userId,
        ?int $zabbixConnectionId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $executionTimeMs = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'zabbix_connection_id' => $zabbixConnectionId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'status' => 'success',
            'execution_time_ms' => $executionTimeMs,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Log a failed action
     */
    public static function logFailure(
        int $userId,
        ?int $zabbixConnectionId,
        string $action,
        string $resourceType,
        ?string $resourceId = null,
        ?string $errorMessage = null,
        ?int $executionTimeMs = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): self {
        return self::create([
            'user_id' => $userId,
            'zabbix_connection_id' => $zabbixConnectionId,
            'action' => $action,
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'execution_time_ms' => $executionTimeMs,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);
    }
}
