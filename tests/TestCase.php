<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Prevent any external HTTP calls during tests
        Http::preventStrayRequests();

        // Provide a safe default fake for MCP server requests
        Http::fake([
            '*' => Http::response([
                'error' => [
                    'message' => 'MCP server not available in testing environment',
                ],
            ], 503),
        ]);
    }
}
