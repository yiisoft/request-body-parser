<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use InvalidArgumentException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandler;
use Yiisoft\Request\Body\BadRequestHandlerInterface;
use Yiisoft\Request\Body\Parser\JsonParser;
use Yiisoft\Request\Body\ParserException;
use Yiisoft\Request\Body\RequestBodyParser;
use Yiisoft\Test\Support\Container\SimpleContainer;

final class RequestBodyParsersTest extends TestCase
{
    public function testWithParser(): void
    {
        $expectedOutput = ['test' => 'value'];

        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId, $expectedOutput);

        $mimeType = 'test/test';
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withParser($mimeType, $containerId);

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest($mimeType), $requestHandler);

        $this->assertSame($expectedOutput, $requestHandler->getRequestParsedBody());
    }

    public function testWithParserWithEmptyParserClass(): void
    {
        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId, '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parser class cannot be an empty string.');
        $this
            ->getRequestBodyParser($container)
            ->withParser('content/future', '');
    }

    public function testWithoutParsers(): void
    {
        $container = $this->getContainer();
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withoutParsers();

        $rawBody = '{"test":"value"}';

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertNull($requestHandler->getRequestParsedBody());
    }

    public function testWithoutParser(): void
    {
        $container = $this->getContainer();
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withoutParsers('application/json');

        $rawBody = '{"test":"value"}';

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertNull($requestHandler->getRequestParsedBody());
    }

    public function testWithoutBadRequestResponse(): void
    {
        $containerId = 'test';
        $container = $this->getContainerWithParser($containerId, '', true);
        $mimeType = 'test/test';
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withParser($mimeType, $containerId);

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest($mimeType), $requestHandler);

        $this->assertSame(Status::OK, $response->getStatusCode());
    }

    public function testWithDefaultBadRequestResponse(): void
    {
        $container = $this->getContainer();
        $badResponseHandler = $this->createDefaultBadResponseHandler();
        $bodyParser = $this->getRequestBodyParser($container, $badResponseHandler);

        $rawBody = '{"test": invalid json}';

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(Status::TEXTS[Status::BAD_REQUEST] . "\n" . 'Invalid JSON data in request body: Syntax error', (string)$response->getBody());
    }

    public function testWithCustomBadRequestResponse(): void
    {
        $container = $this->getContainer();

        $customBody = 'custom response';
        $badResponseHandler = $this->createCustomBadResponseHandler($customBody);

        $bodyParser = $this->getRequestBodyParser($container, $badResponseHandler);

        $rawBody = '{"test": invalid json}';
        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame($customBody, (string)$response->getBody());
    }

    public function testThrownExceptionWithNotExistsParser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parser "invalidParser" cannot be found.');

        $this
            ->getRequestBodyParser($this->getContainer())
            ->withParser('test/test', 'invalidParser');
    }

    public function testThrownExceptionWithInvalidMimeType(): void
    {
        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId, '');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mime type.');

        $this
            ->getRequestBodyParser($container)
            ->withParser('invalid mimeType', $containerId);
    }

    private function getContainer(): SimpleContainer
    {
        return new SimpleContainer(
            [
                JsonParser::class => new JsonParser(),
            ]
        );
    }

    private function getContainerWithParser(string $id, $expectedOutput, bool $throwException = false): SimpleContainer
    {
        return new SimpleContainer(
            [
                $id => new MockParser($expectedOutput, $throwException),
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

        return new class ($mockResponse) implements RequestHandlerInterface {
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

    private function getRequestBodyParser(
        SimpleContainer $container,
        BadRequestHandlerInterface $badRequestHandler = null
    ): RequestBodyParser {
        return new RequestBodyParser($container, $badRequestHandler);
    }

    private function createDefaultBadResponseHandler(): BadRequestHandler
    {
        return new BadRequestHandler(new Psr17Factory());
    }

    private function createCustomBadResponseHandler(string $body): BadRequestHandlerInterface
    {
        return new class ($body, new Psr17Factory()) implements BadRequestHandlerInterface {
            private string $body;
            private ResponseFactoryInterface $responseFactory;

            public function __construct(string $body, ResponseFactoryInterface $responseFactory)
            {
                $this->body = $body;
                $this->responseFactory = $responseFactory;
            }

            public function handle(ServerRequestInterface $request, ?ParserException $e = null): ResponseInterface
            {
                $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
                $response
                    ->getBody()
                    ->write($this->body);
                return $response;
            }
        };
    }
}
