<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Forms response when there is an error parsing request.
 */
interface BadRequestHandlerInterface
{
    /**
     * Handles a `ParserException` object and produces a response.
     *
     * @param ParserException $e Exception occurred during parsing request.
     */
    public function handle(ServerRequestInterface $request, ?ParserException $e = null): ResponseInterface;
}
