<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @extends Model<ZabbixConnection>
 */
class ZabbixConnection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'url',
        'encrypted_token',
        'environment',
        'is_active',
        'max_requests_per_minute',
        'timeout_seconds',
        'last_connection_test',
        'connection_status',
        'count', // For aggregate queries
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'max_requests_per_minute' => 'integer',
        'timeout_seconds' => 'integer',
        'last_connection_test' => 'datetime',
        'connection_status' => 'string',
        'count' => 'integer', // For aggregate queries
    ];

    protected $hidden = [
        'encrypted_token',
    ];

    /**
     * Get the token attribute (decrypted)
     */
    protected function token(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->encrypted_token,
            set: fn ($value) => $this->attributes['encrypted_token'] = $value,
        );
    }

    /**
     * Get the templates for this connection
     */
    /**
     * @return HasMany<ZabbixTemplate>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(ZabbixTemplate::class);
    }

    /**
     * Get the hosts for this connection
     *
     * @return HasMany<ZabbixHost>
     */
    public function hosts(): HasMany
    {
        return $this->hasMany(ZabbixHost::class);
    }

    /**
     * Get the audit logs for this connection
     *
     * @return HasMany<AuditLog>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get the background jobs for this connection
     *
     * @return HasMany<BackgroundJob>
     */
    public function backgroundJobs(): HasMany
    {
        return $this->hasMany(BackgroundJob::class);
    }

    /**
     * Scope for active connections
     */
    /**
     * @param  Builder<ZabbixConnection>  $query
     * @return Builder<ZabbixConnection>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for connections by environment
     */
    /**
     * @param  Builder<ZabbixConnection>  $query
     * @return Builder<ZabbixConnection>
     */
    public function scopeByEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where('environment', $environment);
    }

    /**
     * Test connection to Zabbix server
     */
    public function testConnection(): bool
    {
        try {
            // TODO: Implement actual connection test
            // This will be implemented when we create the MCP client
            $this->update([
                'last_connection_test' => now(),
                'connection_status' => 'active',
            ]);

            return true;
        } catch (\Exception $e) {
            $this->update([
                'last_connection_test' => now(),
                'connection_status' => 'error',
            ]);

            return false;
        }
    }
}
