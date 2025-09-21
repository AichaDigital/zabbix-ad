<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TemplateOptimizationRule extends Model
{
    protected $fillable = [
        'name',
        'description',
        'environment',
        'template_pattern',
        'history_from',
        'history_to',
        'trends_from',
        'trends_to',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope for active rules
     *
     * @param  Builder<TemplateOptimizationRule>  $query
     * @return Builder<TemplateOptimizationRule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for rules by environment
     *
     * @param  Builder<TemplateOptimizationRule>  $query
     * @return Builder<TemplateOptimizationRule>
     */
    public function scopeByEnvironment(Builder $query, string $environment): Builder
    {
        return $query->where(function (Builder $q) use ($environment): void {
            $q->where('environment', 'all')
                ->orWhere('environment', $environment);
        });
    }

    /**
     * Scope for rules matching template pattern
     *
     * @param  Builder<TemplateOptimizationRule>  $query
     * @return Builder<TemplateOptimizationRule>
     */
    public function scopeMatchingTemplate(Builder $query, string $templateName): Builder
    {
        return $query->where(function (Builder $q) use ($templateName): void {
            $q->whereNull('template_pattern')
                ->orWhere('template_pattern', '')
                ->orWhereRaw('? LIKE template_pattern', [$templateName]);
        });
    }

    /**
     * Check if rule applies to a template
     */
    public function appliesToTemplate(string $templateName, string $environment): bool
    {
        // Check environment
        if ($this->environment !== 'all' && $this->environment !== $environment) {
            return false;
        }

        // Check if rule is active
        if (! $this->is_active) {
            return false;
        }

        // Check template pattern
        if (empty($this->template_pattern)) {
            return true;
        }

        // Simple pattern matching (can be enhanced with regex)
        return fnmatch($this->template_pattern, $templateName);
    }

    /**
     * Get optimization settings for a template
     *
     * @return array<string, mixed>
     */
    public function getOptimizationSettings(): array
    {
        return [
            'history_from' => $this->history_from,
            'history_to' => $this->history_to,
            'trends_from' => $this->trends_from,
            'trends_to' => $this->trends_to,
        ];
    }

    /**
     * Calculate potential savings
     *
     * @return array<string, mixed>
     */
    public function getPotentialSavings(): array
    {
        $historyFromDays = $this->parseRetentionDays($this->history_from);
        $historyToDays = $this->parseRetentionDays($this->history_to);
        $trendsFromDays = $this->parseRetentionDays($this->trends_from);
        $trendsToDays = $this->parseRetentionDays($this->trends_to);

        $historyReduction = max(0, $historyFromDays - $historyToDays);
        $trendsReduction = max(0, $trendsFromDays - $trendsToDays);

        return [
            'history_reduction_days' => $historyReduction,
            'trends_reduction_days' => $trendsReduction,
            'history_reduction_percentage' => $historyFromDays > 0 ? round(($historyReduction / $historyFromDays) * 100, 2) : 0,
            'trends_reduction_percentage' => $trendsFromDays > 0 ? round(($trendsReduction / $trendsFromDays) * 100, 2) : 0,
        ];
    }

    /**
     * Parse retention string to days
     */
    private function parseRetentionDays(?string $retention): int
    {
        if ($retention === null) {
            return 0;
        }

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
     * Get rule summary
     *
     * @return array<string, mixed>
     */
    public function getSummary(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'environment' => $this->environment,
            'template_pattern' => $this->template_pattern,
            'is_active' => $this->is_active,
            'optimization_settings' => $this->getOptimizationSettings(),
            'potential_savings' => $this->getPotentialSavings(),
        ];
    }
}
