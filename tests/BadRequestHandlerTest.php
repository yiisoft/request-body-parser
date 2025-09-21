<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandler;

final class BadRequestHandlerTest extends TestCase
{
    public function testShouldReturnCode400(): void
    {
        $response = $this
            ->createHandler()
            ->handle($this->createRequest());
        $this->assertEquals(Status::BAD_REQUEST, $response->getStatusCode());
    }

    public function testShouldReturnCorrectErrorInBody(): void
    {
        $response = $this
            ->createHandler()
            ->handle($this->createRequest());
        $this->assertEquals(Status::TEXTS[Status::BAD_REQUEST], (string)$response->getBody());
    }

    private function createHandler(): BadRequestHandler
    {
        return new BadRequestHandler(new ResponseFactory());
    }

    private function createRequest(string $uri = '/'): ServerRequestInterface
    {
        return new ServerRequest(uri: $uri);
    }
}
