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
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
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
        $container = $this->getContainerWithResponseFactory();
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
        $container = $this->getContainerWithResponseFactory();
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withoutParsers('application/json');

        $rawBody = '{"test":"value"}';

        $requestHandler = $this->createHandler();
        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertNull($requestHandler->getRequestParsedBody());
    }

    public function testWithBadRequestResponse(): void
    {
        $container = $this->getContainerWithResponseFactory();
        $bodyParser = $this->getRequestBodyParser($container);

        $rawBody = '{"test": invalid json}';

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $requestHandler);

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(Status::TEXTS[Status::BAD_REQUEST] . "\nInvalid JSON data in request body: Syntax error", (string)$response->getBody());
    }

    public function testWithoutBadRequestResponse(): void
    {
        $containerId = 'test';
        $container = $this->getContainerWithParser($containerId, '', true);
        $mimeType = 'test/test';
        $bodyParser = $this
            ->getRequestBodyParser($container)
            ->withParser($mimeType, $containerId)
            ->ignoreBadRequestBody();

        $requestHandler = $this->createHandler();
        $response = $bodyParser->process($this->createMockRequest($mimeType), $requestHandler);

        $this->assertSame(Status::OK, $response->getStatusCode());
    }

    public function testWithCustomBadRequestResponse(): void
    {
        $container = $this->getContainerWithResponseFactory();

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
            ->getRequestBodyParser($this->getContainerWithResponseFactory())
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

    private function getContainerWithResponseFactory(): SimpleContainer
    {
        return new SimpleContainer(
            [
                ResponseFactoryInterface::class => static function () {
                    return new Psr17Factory();
                },
                JsonParser::class => new JsonParser(),
            ]
        );
    }

    private function getContainerWithParser(string $id, $expectedOutput, bool $throwException = false): SimpleContainer
    {
        return new SimpleContainer(
            [
                ResponseFactoryInterface::class => $this->createMock(ResponseFactoryInterface::class),
                $id => new MockParser($expectedOutput, $throwException),
            ]
        );
    }

    private function createMockRequest(string $contentType, string|null $rawBody = null): ServerRequestInterface
    {
        if ($rawBody !== null) {
            $body = $this->createMock(StreamInterface::class);
            $body
                ->method('__toString')
                ->willReturn($rawBody);
        }

        return new ServerRequest('POST', '/', [Header::CONTENT_TYPE => $contentType], $body ?? null);
    }

    private function createHandler(): BadRequestHandlerInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getStatusCode')
            ->willReturn(Status::OK);

        return new class ($mockResponse) implements BadRequestHandlerInterface {
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

            public function withParserException(ParserException $e): BadRequestHandlerInterface
            {
                // do nothing
                return $this;
            }
        };
    }

    private function getFactory(): ResponseFactoryInterface
    {
        return new Psr17Factory();
    }

    private function getRequestBodyParser(
        SimpleContainer $container,
        BadRequestHandlerInterface|null $badRequestHandler = null
    ): RequestBodyParser {
        return new RequestBodyParser($this->getFactory(), $container, $badRequestHandler);
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

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
                $response
                    ->getBody()
                    ->write($this->body);
                return $response;
            }

            public function withParserException(ParserException $e): BadRequestHandlerInterface
            {
                // do nothing
                return $this;
            }
        };
    }
}
