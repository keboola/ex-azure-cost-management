<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;

class UnexpectedColumnException extends \Exception implements UserExceptionInterface
{

}
