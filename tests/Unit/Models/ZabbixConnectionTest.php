<?php

use App\Models\ZabbixConnection;

uses()->group('unit', 'models');

it('supports token getter/setter round-trip', function () {
    $connection = ZabbixConnection::factory()->create();

    $plain = 'my-secret-token-xyz';
    $connection->token = $plain; // writes to encrypted_token attribute
    $connection->save();
    $connection->refresh();

    // Round-trip: accessor returns what was set
    expect($connection->token)->toBe($plain);

    // Ensure the raw attribute exists (hidden from arrays/json)
    expect($connection->getAttributes())
        ->toHaveKey('encrypted_token');
});
