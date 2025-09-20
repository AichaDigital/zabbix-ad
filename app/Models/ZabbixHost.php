<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @extends Model<ZabbixHost>
 */
class ZabbixHost extends Model
{
    use HasFactory;

    protected $fillable = [
        'zabbix_connection_id',
        'host_id',
        'host_name',
        'visible_name',
        'ip_address',
        'status',
        'available',
        'templates_count',
        'items_count',
        'last_check',
        'last_sync',
        'count', // For aggregate queries
    ];

    protected $casts = [
        'templates_count' => 'integer',
        'items_count' => 'integer',
        'last_check' => 'datetime',
        'last_sync' => 'datetime',
        'count' => 'integer', // For aggregate queries
    ];

    /**
     * Get the connection that owns this host
     */
    /**
     * @return BelongsTo<ZabbixConnection, ZabbixHost>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZabbixConnection::class, 'zabbix_connection_id');
    }

    /**
     * Scope for enabled hosts
     */
    /**
     * @param  Builder<ZabbixHost>  $query
     * @return Builder<ZabbixHost>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('status', 'enabled');
    }

    /**
     * Scope for available hosts
     */
    /**
     * @param  Builder<ZabbixHost>  $query
     * @return Builder<ZabbixHost>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('available', 'available');
    }

    /**
     * Scope for hosts by status
     */
    /**
     * @param  Builder<ZabbixHost>  $query
     * @return Builder<ZabbixHost>
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for hosts by availability
     */
    /**
     * @param  Builder<ZabbixHost>  $query
     * @return Builder<ZabbixHost>
     */
    public function scopeByAvailability(Builder $query, string $availability): Builder
    {
        return $query->where('available', $availability);
    }

    /**
     * Get host display name
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->visible_name ?: $this->host_name;
    }

    /**
     * Get host status badge color
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'enabled' => 'success',
            'disabled' => 'danger',
            'maintenance' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Get availability badge color
     */
    public function getAvailabilityColorAttribute(): string
    {
        return match ($this->available) {
            'available' => 'success',
            'unavailable' => 'danger',
            'unknown' => 'warning',
            default => 'secondary',
        };
    }

    /**
     * Check if host is healthy
     */
    public function isHealthy(): bool
    {
        return $this->status === 'enabled' && $this->available === 'available';
    }

    /**
     * Get host health status
     */
    public function getHealthStatus(): array
    {
        return [
            'is_healthy' => $this->isHealthy(),
            'status' => $this->status,
            'availability' => $this->available,
            'last_check' => $this->last_check,
            'templates_count' => $this->templates_count,
            'items_count' => $this->items_count,
        ];
    }

    /**
     * Update host statistics
     */
    public function updateStatistics(array $stats): void
    {
        $this->update([
            'templates_count' => $stats['templates_count'] ?? $this->templates_count,
            'items_count' => $stats['items_count'] ?? $this->items_count,
            'last_check' => now(),
            'last_sync' => now(),
        ]);
    }
}
