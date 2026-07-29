<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use RuntimeException;

/**
 * Exception during parsing request.
 *
 * @psalm-suppress ClassMustBeFinal We want to allow extending this exception.
 */
class ParserException extends RuntimeException {}
