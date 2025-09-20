<?php

use App\Jobs\Zabbix\SyncZabbixDataJob;
use App\Models\BackgroundJob;
use App\Models\ZabbixConnection;
use App\Services\Zabbix\ZabbixSyncService;

uses()->group('feature', 'jobs');

it('marks background job as completed on success using a fake service', function () {
    $connection = ZabbixConnection::factory()->create([
        'name' => 'Local Test',
        'url' => 'http://localhost:8080',
        'connection_status' => 'active',
    ]);

    // Bind a fake ZabbixSyncService so the job uses it
    $fakeServiceClass = new class($connection) extends ZabbixSyncService
    {
        public function __construct(private ZabbixConnection $c)
        {
            parent::__construct($c);
        }

        public function getSyncStats(): array
        {
            return ['templates_count' => 0, 'hosts_count' => 0, 'last_sync' => null, 'connection_status' => 'active'];
        }

        public function syncTemplates(): array
        {
            return ['synced' => 2, 'errors' => 0];
        }

        public function syncHosts(): array
        {
            return ['synced' => 3, 'errors' => 0];
        }
    };

    app()->bind(ZabbixSyncService::class, fn ($app, $params) => new ($fakeServiceClass::class)($params['connection']));

    $job = new SyncZabbixDataJob($connection);
    // Directly call handle to avoid dispatching/queue processing in tests
    $job->handle();

    $record = BackgroundJob::latest('id')->first();
    expect($record)
        ->not->toBeNull()
        ->and($record->status)->toBe('completed')
        ->and($record->result_data['templates_synced'])->toBe(2)
        ->and($record->result_data['hosts_synced'])->toBe(3)
        ->and($record->result_data['connection_status'])->toBe('active');
});

it('marks background job as failed on exception', function () {
    $connection = ZabbixConnection::factory()->create([
        'name' => 'Local Test',
        'url' => 'http://localhost:8080',
        'connection_status' => 'active',
    ]);

    // Fake that throws on syncTemplates
    $throwingServiceClass = new class($connection) extends ZabbixSyncService
    {
        public function __construct(private ZabbixConnection $c)
        {
            parent::__construct($c);
        }

        public function getSyncStats(): array
        {
            return ['templates_count' => 0, 'hosts_count' => 0, 'last_sync' => null, 'connection_status' => 'active'];
        }

        public function syncTemplates(): array
        {
            throw new Exception('boom');
        }
    };

    app()->bind(ZabbixSyncService::class, fn ($app, $params) => new ($throwingServiceClass::class)($params['connection']));

    $job = new SyncZabbixDataJob($connection);

    expect(fn () => $job->handle())->toThrow(Exception::class);

    $record = BackgroundJob::latest('id')->first();
    expect($record)
        ->not->toBeNull()
        ->and($record->status)->toBe('failed')
        ->and($record->error_message)->toBe('boom');
});
