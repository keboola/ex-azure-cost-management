<?php

declare(strict_types=1);

namespace Keboola\AzureCostExtractor\Exception;

use Keboola\CommonExceptions\UserExceptionInterface;

class AccessTokenRefreshException extends \Exception implements UserExceptionInterface
{

}
