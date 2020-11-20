<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use Psr\Http\Server\RequestHandlerInterface;

interface BadRequestHandlerInterface extends RequestHandlerInterface
{
    public function withParserException(ParserException $e): self;
}
