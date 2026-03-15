<?php

declare(strict_types=1);

namespace Apacheborys\KeycloakPhpClient\Tests\Support\Http;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

final class NativePsr18ClientException extends RuntimeException implements ClientExceptionInterface
{
}
