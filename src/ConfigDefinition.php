<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class ConfigDefinition extends BaseConfigDefinition
{
    public const DEFAULT_MAX_TRIES = 5;
    public const TIME_FRAME_CUSTOM = 'Custom';
    public const TYPE_VALUES = [
        'ActualCost',
        'AmortizedCost',
        'Usage',
    ];
    public const AGGREGATION_VALUES = [
        'Cost',
        'CostUSD',
        'PreTaxCostUSD',
        'UsageQuantity',
        'PreTaxCost',
    ];
    public const GRANULARITY_VALUES = [
        'None',
        'Daily',
        'Monthly',
    ];
    public const TIME_FRAME_VALUES = [
        'WeekToDate',
        'MonthToDate',
        'BillingMonthToDate',
        'TheLastMonth',
        'TheLastBillingMonth',
        self::TIME_FRAME_CUSTOM,
    ];
    public const GROUPING_DIMENSION_VALUES = [
        'ServiceName',
        'ResourceGroupName',
        'ResourceLocation',
        'ResourceType',
        'ResourceId',
        'MeterCategory',
        'MeterSubCategory',
        'Meter',
        'ServiceTier',
        'BillingPeriod',
        'InvoiceNumber',
        'PartNumber',
        'PricingModel',
        'ChargeType',
        'PublisherType',
        'ReservationId',
        'ReservationName',
        'Frequency',
        'ResourceGuid',
    ];

    protected function getRootDefinition(TreeBuilder $treeBuilder): ArrayNodeDefinition
    {
        $rootNode = parent::getRootDefinition($treeBuilder);

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $rootNode
            ->children()
                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end();
        // @formatter:on

        return $rootNode;
    }

    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();

        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->isRequired()
            ->children()
                ->scalarNode('subscriptionId')->isRequired()->cannotBeEmpty()->end()
                ->integerNode('maxTries')->min(1)->defaultValue(self::DEFAULT_MAX_TRIES)->end()
                ->arrayNode('export')
                    ->isRequired()
                    ->children()
                        ->enumNode('type')
                            ->values(self::TYPE_VALUES)
                            ->defaultValue('ActualCost')
                        ->end()
                        ->enumNode('aggregation')
                            ->values(self::AGGREGATION_VALUES)
                            ->defaultValue('Cost')
                        ->end()
                        ->enumNode('granularity')
                            ->values(self::GRANULARITY_VALUES)
                            ->defaultValue('Daily')
                        ->end()
                        ->booleanNode('incremental')
                            ->defaultValue(true)
                        ->end()
                        ->arrayNode('timeDimension')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->enumNode('timeFrame')
                                    ->values(self::TIME_FRAME_VALUES)
                                    ->defaultValue('MonthToDate')
                                ->end()
                                ->scalarNode('start')->defaultNull()->cannotBeEmpty()->end()
                                ->scalarNode('end')->defaultNull()->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                        ->arrayNode('groupingDimensions')
                            ->isRequired()
                            ->requiresAtLeastOneElement()
                            ->enumPrototype()
                                ->values(self::GROUPING_DIMENSION_VALUES)
                            ->end()
                        ->end()
                    ->end()
               ->end()
            ->end();
        // @formatter:on

        $parametersNode->validate()->always(function (array $parameters): array {
            return $this->validate($parameters);
        });

        return $parametersNode;
    }

    protected function validate(array $parameters): array
    {
        $timeDimension = $parameters['export']['timeDimension'] ?? [];
        $timeFrame = $timeDimension['timeFrame'] ?? null;
        $customTimeFrame = $timeFrame === self::TIME_FRAME_CUSTOM;

        // Custom timeFrame, but missing start or end
        if ($customTimeFrame && (!isset($timeDimension['start']) || !isset($timeDimension['end']))) {
            throw new InvalidConfigurationException(sprintf(
                'Missing configuration parameters "parameters.export.timeDimension.start/end" for timeFrame="%s".',
                $timeFrame
            ));
        }

        // Not custom timeFrame, but start or end is set
        if (!$customTimeFrame && (isset($timeDimension['start']) || isset($timeDimension['end']))) {
            throw new InvalidConfigurationException(sprintf(
                'Configuration parameters "parameters.export.timeDimension.start/end" ' .
                'are not compatible with timeFrame="%s".',
                $timeFrame
            ));
        }

        foreach (['start', 'end'] as $key) {
            if (isset($timeDimension[$key]) and !preg_match('~^\d{4}-\d{2}-\d{2}$~', $timeDimension[$key])) {
                throw new InvalidConfigurationException(sprintf(
                    'Invalid date "%s" in "parameters.export.timeDimension.%s", please use "YYYY-MM-DD" format.',
                    $timeDimension[$key],
                    $key
                ));
            }
        }

        return $parameters;
    }
}
