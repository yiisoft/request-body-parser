<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;

/**
 * Default handler that is used when there is an error during parsing a request.
 */
final class BadRequestHandler implements BadRequestHandlerInterface
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory
    ) {
    }

    public function handle(ServerRequestInterface $request, ?ParserException $e = null): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
        $response
            ->getBody()
            ->write(Status::TEXTS[Status::BAD_REQUEST]);

        if ($e !== null) {
            $response
                ->getBody()
                ->write("\n" . $e->getMessage());
        }

        return $response;
    }
}
