<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests\Support;

use HttpSoft\Message\Response;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RequestCatcher implements RequestHandlerInterface
{
    private ServerRequestInterface|null $request = null;

    public function isCaught(): bool
    {
        return $this->request !== null;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $this->request = $request;
        return new Response();
    }
}
