<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Tests;

use Generator;
use Keboola\AzureCostExtractor\Config;
use Keboola\AzureCostExtractor\ConfigDefinition;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigTest extends BaseTest
{
    /**
     * @dataProvider getValidConfigs
     */
    public function testValidConfig(array $config): void
    {
        new Config($config, new ConfigDefinition());
        $this->expectNotToPerformAssertions();
    }

    /**
     * @dataProvider getInvalidConfigs
     */
    public function testInvalidConfig(array $config, string $expectedMessage): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);
        new Config($config, new ConfigDefinition());
    }

    public function getValidConfigs(): Generator
    {
        yield 'full' => [
            [
                'parameters' => [
                    'subscriptionId' => '1234',
                    'maxTries' => 4,
                    'export' => [
                        'destination' => 'destination-table',
                        'type' => 'ActualCost',
                        'granularity' => 'Daily',
                        'incremental' => false,
                        'timeDimension' => [
                            'timeFrame' => ConfigDefinition::TIME_FRAME_CUSTOM,
                            'start' => '2020-01-01',
                            'end' => '2020-01-31',
                        ],
                        'groupingDimensions' => ConfigDefinition::GROUPING_DIMENSION_VALUES,
                    ],
                ],
            ],
        ];

        yield 'minimal' => [
            $this->getValidMinimalConfig(),
        ];
    }

    public function getInvalidConfigs(): Generator
    {
        yield 'empty' => [
            [],
            'The child node "parameters" at path "root" must be configured.',
        ];

        yield 'empty-parameters' => [
            ['parameters' => []],
            'The child node "subscriptionId" at path "root.parameters" must be configured.',
        ];

        $config = $this->getValidMinimalConfig();
        $config['parameters']['export']['type'] = 'ABCDEF';
        yield 'invalid-type' => [
            $config,
            'The value "ABCDEF" is not allowed for path "root.parameters.export.type". ' .
            'Permissible values: "ActualCost", "AmortizedCost", "Usage"',
        ];

        $config = $this->getValidMinimalConfig();
        $config['parameters']['export']['granularity'] = 'ABCDEF';
        yield 'invalid-granularity' => [
            $config,
            'The value "ABCDEF" is not allowed for path "root.parameters.export.granularity". ' .
            'Permissible values: "None", "Daily", "Monthly"',
        ];

        $config = $this->getValidMinimalConfig();
        $config['parameters']['export']['timeDimension']['timeFrame'] = 'ABCDEF';
        yield 'invalid-time-frame' => [
            $config,
            'The value "ABCDEF" is not allowed for path "root.parameters.export.timeDimension.timeFrame". ' .
            'Permissible values: "WeekToDate", "MonthToDate", "BillingMonthToDate", ' .
            '"TheLastMonth", "TheLastBillingMonth", "Custom"',
        ];

        $config = $this->getValidMinimalConfig();
        $config['parameters']['export']['timeDimension']['timeFrame'] = 'MonthToDate';
        $config['parameters']['export']['timeDimension']['start'] = '2020-01-01';
        $config['parameters']['export']['timeDimension']['end'] = '2020-02-31';
        yield 'invalid-time-frame-and-start-end' => [
            $config,
            'Configuration parameters "parameters.export.timeDimension.start/end" ' .
            'are not compatible with timeFrame="MonthToDate", please use timeFrame="Custom".',
        ];

        $config = $this->getValidMinimalConfig();
        $config['parameters']['export']['timeDimension']['timeFrame'] = ConfigDefinition::TIME_FRAME_CUSTOM;
        yield 'invalid-time-frame-custom-without-start-end' => [
            $config,
            'Missing configuration parameters "parameters.export.timeDimension.start/end" for timeFrame="Custom".',
        ];
    }

    private function getValidMinimalConfig(): array
    {
        return [
            'parameters' => [
                'subscriptionId' => '1234',
                'export' => [
                    'destination' => 'destination-table',
                    'groupingDimensions' => ['ServiceName'],
                ],
            ],
        ];
    }
}
