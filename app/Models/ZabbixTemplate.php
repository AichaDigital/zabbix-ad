<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @extends Model<ZabbixTemplate>
 */
class ZabbixTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'zabbix_connection_id',
        'template_id',
        'name',
        'description',
        'template_type',
        'items_count',
        'triggers_count',
        'history_retention',
        'trends_retention',
        'is_optimized',
        'last_sync',
        'count', // For aggregate queries
    ];

    protected $casts = [
        'items_count' => 'integer',
        'triggers_count' => 'integer',
        'is_optimized' => 'boolean',
        'last_sync' => 'datetime',
        'count' => 'integer', // For aggregate queries
    ];

    /**
     * Get the connection that owns this template
     */
    /**
     * @return BelongsTo<ZabbixConnection, ZabbixTemplate>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZabbixConnection::class, 'zabbix_connection_id');
    }

    /**
     * Scope for optimized templates
     */
    /**
     * @param  Builder<ZabbixTemplate>  $query
     * @return Builder<ZabbixTemplate>
     */
    public function scopeOptimized(Builder $query): Builder
    {
        return $query->where('is_optimized', true);
    }

    /**
     * Scope for templates by type
     */
    /**
     * @param  Builder<ZabbixTemplate>  $query
     * @return Builder<ZabbixTemplate>
     */
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('template_type', $type);
    }

    /**
     * Scope for templates that need optimization
     */
    /**
     * @param  Builder<ZabbixTemplate>  $query
     * @return Builder<ZabbixTemplate>
     */
    public function scopeNeedsOptimization(Builder $query): Builder
    {
        return $query->where('is_optimized', false)
            ->where('template_type', '!=', 'system');
    }

    /**
     * Get optimization potential based on retention settings
     */
    public function getOptimizationPotential(): array
    {
        $historyDays = $this->parseRetentionDays($this->history_retention);
        $trendsDays = $this->parseRetentionDays($this->trends_retention);

        $historyReduction = max(0, $historyDays - 7); // Target: 7 days
        $trendsReduction = max(0, $trendsDays - 30); // Target: 30 days

        return [
            'history_reduction_days' => $historyReduction,
            'trends_reduction_days' => $trendsReduction,
            'history_reduction_percentage' => $historyDays > 0 ? round(($historyReduction / $historyDays) * 100, 2) : 0,
            'trends_reduction_percentage' => $trendsDays > 0 ? round(($trendsReduction / $trendsDays) * 100, 2) : 0,
        ];
    }

    /**
     * Parse retention string to days
     */
    private function parseRetentionDays(string $retention): int
    {
        if (preg_match('/(\d+)d/', $retention, $matches)) {
            return (int) $matches[1];
        }

        if (preg_match('/(\d+)w/', $retention, $matches)) {
            return (int) $matches[1] * 7;
        }

        if (preg_match('/(\d+)M/', $retention, $matches)) {
            return (int) $matches[1] * 30;
        }

        if (preg_match('/(\d+)y/', $retention, $matches)) {
            return (int) $matches[1] * 365;
        }

        return 0;
    }

    /**
     * Mark template as optimized
     */
    public function markAsOptimized(): void
    {
        $this->update([
            'is_optimized' => true,
            'last_sync' => now(),
        ]);
    }

    /**
     * Get template statistics
     */
    public function getStatistics(): array
    {
        return [
            'items_count' => $this->items_count,
            'triggers_count' => $this->triggers_count,
            'optimization_potential' => $this->getOptimizationPotential(),
            'last_sync' => $this->last_sync,
            'is_optimized' => $this->is_optimized,
        ];
    }
}
