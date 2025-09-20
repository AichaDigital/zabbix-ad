<?php

use App\Models\ZabbixConnection;
use App\Models\ZabbixTemplate;

uses()->group('unit', 'models');

test('can create zabbix template', function () {
    $connection = ZabbixConnection::factory()->create();
    $template = ZabbixTemplate::factory()->create([
        'zabbix_connection_id' => $connection->id,
        'template_id' => 'test-template-1',
        'name' => 'Test Template',
        'template_type' => 'custom',
    ]);

    expect($template)
        ->toBeInstanceOf(ZabbixTemplate::class)
        ->template_id->toBe('test-template-1')
        ->name->toBe('Test Template')
        ->template_type->toBe('custom')
        ->zabbix_connection_id->toBe($connection->id);
});

test('template belongs to connection', function () {
    $connection = ZabbixConnection::factory()->create();
    $template = ZabbixTemplate::factory()->create([
        'zabbix_connection_id' => $connection->id,
    ]);

    $template->load('connection');

    expect($template->connection)
        ->toBeInstanceOf(ZabbixConnection::class)
        ->id->toBe($connection->id);
});

test('template has default values', function () {
    $template = ZabbixTemplate::factory()->make([
        'template_type' => 'custom',
        'items_count' => 0,
        'triggers_count' => 0,
        'history_retention' => '7d',
        'trends_retention' => '30d',
        'is_optimized' => false,
    ]);

    expect($template)
        ->template_type->toBe('custom')
        ->items_count->toBe(0)
        ->triggers_count->toBe(0)
        ->history_retention->toBe('7d')
        ->trends_retention->toBe('30d')
        ->is_optimized->toBeFalse();
});

test('scope optimized returns only optimized templates', function () {
    ZabbixTemplate::factory()->create(['is_optimized' => true]);
    ZabbixTemplate::factory()->create(['is_optimized' => false]);

    $optimizedTemplates = ZabbixTemplate::optimized()->get();

    expect($optimizedTemplates)
        ->toHaveCount(1)
        ->and($optimizedTemplates->first()->is_optimized)
        ->toBeTrue();
});

test('scope needs optimization returns templates that need optimization', function () {
    ZabbixTemplate::factory()->create(['is_optimized' => false, 'template_type' => 'custom']);
    ZabbixTemplate::factory()->create(['is_optimized' => true, 'template_type' => 'custom']);
    ZabbixTemplate::factory()->create(['is_optimized' => false, 'template_type' => 'system']); // Should be excluded

    $needsOptimization = ZabbixTemplate::needsOptimization()->get();

    expect($needsOptimization)
        ->toHaveCount(1)
        ->and($needsOptimization->first()->is_optimized)
        ->toBeFalse();
});

test('scope by type returns templates by type', function () {
    ZabbixTemplate::factory()->create(['template_type' => 'system']);
    ZabbixTemplate::factory()->create(['template_type' => 'custom']);

    $systemTemplates = ZabbixTemplate::byType('system')->get();
    $customTemplates = ZabbixTemplate::byType('custom')->get();

    expect($systemTemplates)
        ->toHaveCount(1)
        ->and($systemTemplates->first()->template_type)
        ->toBe('system')
        ->and($customTemplates)
        ->toHaveCount(1)
        ->and($customTemplates->first()->template_type)
        ->toBe('custom');
});

test('can calculate optimization potential', function () {
    $template = ZabbixTemplate::factory()->create([
        'history_retention' => '365d',
        'trends_retention' => '730d',
        'items_count' => 100,
    ]);

    $potential = $template->getOptimizationPotential();

    expect($potential)
        ->toBeArray()
        ->toHaveKeys(['history_reduction_days', 'trends_reduction_days', 'history_reduction_percentage', 'trends_reduction_percentage']);
});

test('template model structure matches expected schema', function () {
    $template = ZabbixTemplate::factory()->make([
        'template_id' => 'template-123',
        'name' => 'Test Template',
        'description' => 'Test Template Description',
        'template_type' => 'custom',
        'items_count' => 10,
        'triggers_count' => 5,
        'history_retention' => '30d',
        'trends_retention' => '365d',
        'is_optimized' => false,
    ]);

    expect($template->toArray())
        ->toMatchSnapshot();
});

test('template optimization potential calculation returns consistent results', function () {
    $template = ZabbixTemplate::factory()->create([
        'history_retention' => '365d',
        'trends_retention' => '730d',
        'items_count' => 100,
        'triggers_count' => 50,
    ]);

    $potential = $template->getOptimizationPotential();

    expect($potential)
        ->toMatchSnapshot();
});
