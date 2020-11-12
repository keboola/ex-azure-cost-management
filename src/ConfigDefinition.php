<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    public const DEFAULT_MAX_TRIES = 5;

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
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
