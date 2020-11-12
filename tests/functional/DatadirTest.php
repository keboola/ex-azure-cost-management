<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;

class DatadirTest extends DatadirTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        putenv('OAUTH_DATA=' . json_encode([
            'access_token' => (string) getenv('OAUTH_ACCESS_TOKEN'),
            'refresh_token' => (string) getenv('OAUTH_REFRESH_TOKEN'),
        ]));
    }
}
