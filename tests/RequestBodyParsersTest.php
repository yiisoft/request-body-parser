<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use HttpSoft\Message\ResponseFactory;
use HttpSoft\Message\ServerRequest;
use HttpSoft\Message\StreamFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\TestWith;
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
use Yiisoft\Request\Body\Tests\Support\RequestCatcher;
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
        $this->assertSame(Status::TEXTS[Status::BAD_REQUEST] . "\nInvalid JSON data in request body: Syntax error", (string) $response->getBody());
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
        $this->assertSame($customBody, (string) $response->getBody());
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

    #[TestWith(['myapp/json', 'myapp/json'])]
    #[TestWith(['myapp/json', 'myapp/json; charset=utf-8'])]
    #[TestWith(['myapp/json', 'MYAPP/JSON; charset=utf-8'])]
    #[TestWith(['myapp/json', 'MYAPP/JSON ; charset=utf-8'])]
    #[TestWith([' myapp/json ', 'myapp/json'])]
    #[TestWith(['MYAPP/JSON', 'myapp/json'])]
    #[TestWith(['myapp/json', 'MYAPP/JSON'])]
    public function testMimeTypeNormalization(string $parserType, string $contentType): void
    {
        $middleware = (new RequestBodyParser(
            new ResponseFactory(),
            new SimpleContainer([
                JsonParser::class => new JsonParser(),
            ])
        ))->withParser($parserType, JsonParser::class);
        $request = new ServerRequest(
            headers: [Header::CONTENT_TYPE => $contentType],
            body: (new StreamFactory())->createStream('{"test":"value"}'),
        );
        $catcher = new RequestCatcher();

        $middleware->process($request, $catcher);

        $this->assertTrue($catcher->isCaught());

        $request = $catcher->getRequest();
        $this->assertSame(['test' => 'value'], $request->getParsedBody());
    }

    private function getContainerWithResponseFactory(): SimpleContainer
    {
        return new SimpleContainer(
            [
                ResponseFactoryInterface::class => static function () {
                    return new ResponseFactory();
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
        return new ServerRequest(
            method: 'POST',
            uri: '/',
            headers: [Header::CONTENT_TYPE => $contentType],
            body: $rawBody === null ? null : (new StreamFactory())->createStream($rawBody),
        );
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

    private function getRequestBodyParser(
        SimpleContainer $container,
        BadRequestHandlerInterface|null $badRequestHandler = null
    ): RequestBodyParser {
        return new RequestBodyParser(new ResponseFactory(), $container, $badRequestHandler);
    }

    private function createCustomBadResponseHandler(string $body): BadRequestHandlerInterface
    {
        return new class ($body, new ResponseFactory()) implements BadRequestHandlerInterface {
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
