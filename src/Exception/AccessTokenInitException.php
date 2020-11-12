<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;

class AccessTokenInitException extends \Exception implements UserExceptionInterface
{

}
