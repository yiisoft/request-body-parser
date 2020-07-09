<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Di\Container;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\RequestBodyParsers;

final class RequestBodyParsersTest extends TestCase
{
    public function testAddedRenderer(): void
    {
        $expectedOutput = ['test' => 'value'];

        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId, $expectedOutput);

        $mimeType = 'test/test';
        $bodyParser = $this->getRequestBodyParsers($container)->withAddedParser($mimeType, $containerId);

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest($mimeType), $requestHandler);

        $this->assertSame($expectedOutput, $requestHandler->getRequestParsedBody());
    }

    public function testWithoutParsers(): void
    {
        $container = new Container();
        $bodyParser = $this->getRequestBodyParsers($container)->withoutParsers();

        $rawBody = '{"test":"value"}';

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertNull($requestHandler->getRequestParsedBody());
    }

    public function testWithoutParser(): void
    {
        $container = new Container();
        $bodyParser = $this->getRequestBodyParsers($container)->withoutParsers('application/json');

        $rawBody = '{"test":"value"}';

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertNull($requestHandler->getRequestParsedBody());
    }

    public function testWithBadRequestResponse(): void
    {
        $container = new Container();
        $bodyParser = $this->getRequestBodyParsers($container);

        $rawBody = '{"test": invalid json}';

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertSame($response->getStatusCode(), Status::BAD_REQUEST);
    }

    public function testWithoutBadRequestResponse(): void
    {
        $containerId = 'test';
        $container = $this->getContainerWithParser($containerId, '', true);
        $mimeType = 'test/test';
        $bodyParser = $this
            ->getRequestBodyParsers($container)
            ->withAddedParser($mimeType, $containerId)
            ->withoutBadRequestResponse();

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest($mimeType), $requestHandler);

        $this->assertSame($response->getStatusCode(), Status::OK);
    }

    private function getContainerWithParser(string $id, $expectedOutput, bool $throwException = false): Container
    {
        return new Container(
            [
                $id => new MockParser($expectedOutput, $throwException)
            ]
        );
    }

    private function createMockRequest(string $contentType, string $rawBody = null): ServerRequestInterface
    {
        if ($rawBody !== null) {
            $body = $this->createMock(StreamInterface::class);
            $body
                ->method('__toString')
                ->willReturn($rawBody);
        }

        return new ServerRequest('POST', '/', [Header::CONTENT_TYPE => $contentType], $body ?? null);
    }

    private function createHandler(): RequestHandlerInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getStatusCode')
            ->willReturn(Status::OK);

        return new class($mockResponse) implements RequestHandlerInterface {
            private $requestParsedBody;
            private ResponseInterface $mockResponse;

            public function __construct(ResponseInterface $mockResponse)
            {
                $this->mockResponse = $mockResponse;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->requestParsedBody = $request->getParsedBody();
                return $this->mockResponse;
            }

            /**
             * @return array|object|null
             */
            public function getRequestParsedBody()
            {
                return $this->requestParsedBody;
            }
        };
    }

    private function getFactory(): ResponseFactoryInterface
    {
        return new Psr17Factory();
    }

    private function getRequestBodyParsers(Container $container): RequestBodyParsers
    {
        return new RequestBodyParsers($this->getFactory(), $container);
    }
}
