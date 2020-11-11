<?php

declare(strict_types=1);

namespace AzureCostExtractor\Exception;

use Keboola\CommonExceptions\ApplicationExceptionInterface;

class InvalidAuthDataException extends \Exception implements ApplicationExceptionInterface
{

}
