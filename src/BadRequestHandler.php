<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Status;

final class BadRequestHandler implements RequestHandlerInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(ResponseFactoryInterface $responseFactory)
    {
        $this->responseFactory = $responseFactory;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
        $response->getBody()->write(Status::TEXTS[Status::BAD_REQUEST]);
        return $response;
    }
}
