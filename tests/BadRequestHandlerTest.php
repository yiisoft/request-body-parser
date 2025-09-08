<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Http\Method;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandler;
use Yiisoft\Request\Body\ParserException;

final class BadRequestHandlerTest extends TestCase
{
    public function testShouldReturnCode400AndCorrectErrorInBody(): void
    {
        $response = $this
            ->createHandler()
            ->handle($this->createRequest());

        $this->assertEquals(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals(Status::TEXTS[Status::BAD_REQUEST], (string)$response->getBody());
    }

    public function testShouldReturnCode400AndExceptionMessageInBody(): void
    {
        $e = new ParserException('JGURDA');
        $response = $this
            ->createHandler()
            ->handle($this->createRequest(), $e);

        $this->assertEquals(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertEquals(Status::TEXTS[Status::BAD_REQUEST] . "\n" . $e->getMessage(), (string)$response->getBody());
    }

    private function createHandler(): BadRequestHandler
    {
        return new BadRequestHandler(new Psr17Factory());
    }

    private function createRequest(string $uri = '/'): ServerRequestInterface
    {
        return new ServerRequest(Method::GET, $uri);
    }
}
