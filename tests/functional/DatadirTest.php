<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\FunctionalTests;

use Keboola\DatadirTests\DatadirTestCase;
use Keboola\DatadirTests\DatadirTestSpecificationInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Process\Process;

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

    protected function assertMatchesSpecification(
        DatadirTestSpecificationInterface $specification,
        Process $runProcess,
        string $tempDatadir
    ): void {
        // Remove state.json, we cannot check it, it contains a dynamic new tokens, see OAuthTest
        @unlink($tempDatadir . '/out/state.json');

        // Clear CSV files, they contain random usage/cost data, we check only manifests
        $finder = new Finder();
        foreach ($finder->files()->in($tempDatadir . '/out/tables')->name(['*.csv']) as $csvFile) {
            file_put_contents($csvFile->getPathname(), "\"random usage data was removed\"\n");
        }

        parent::assertMatchesSpecification($specification, $runProcess, $tempDatadir);
    }
}
