<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

/**
 * Exception during parsing request.
 *
 * @psalm-suppress ClassMustBeFinal We want to allow extending this exception.
 */
final class ParserException extends \RuntimeException
{
}
