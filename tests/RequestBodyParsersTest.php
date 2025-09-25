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
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\BadRequestHandler;
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
        $bodyParser = $this->getRequestBodyParser($container)
            ->withParser($mimeType, $containerId);
        $catcher = new RequestCatcher();

        $bodyParser->process($this->createMockRequest($mimeType), $catcher);
        $request = $catcher->getRequest();

        $this->assertSame($expectedOutput, $request->getParsedBody());
    }

    public function testWithParserWithEmptyParserClass(): void
    {
        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parser class cannot be an empty string.');
        $this->getRequestBodyParser($container)
            ->withParser('content/future', '');
    }

    public function testWithoutParsers(): void
    {
        $bodyParser = $this->getRequestBodyParser($this->getContainer())
            ->withoutParsers();

        $rawBody = '{"test":"value"}';
        $catcher = new RequestCatcher();

        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $catcher);
        $request = $catcher->getRequest();

        $this->assertNull($request->getParsedBody());
    }

    public function testWithoutParser(): void
    {
        $bodyParser = $this->getRequestBodyParser($this->getContainer())
            ->withoutParsers('application/json');

        $rawBody = '{"test":"value"}';
        $catcher = new RequestCatcher();

        $bodyParser->process($this->createMockRequest('application/json', $rawBody), $catcher);
        $request = $catcher->getRequest();

        $this->assertNull($request->getParsedBody());
    }

    public function testWithoutBadRequestResponse(): void
    {
        $containerId = 'test';
        $container = $this->getContainerWithParser($containerId, throwException: true);
        $mimeType = 'test/test';
        $bodyParser = $this->getRequestBodyParser($container)
            ->withParser($mimeType, $containerId);
        $catcher = new RequestCatcher();

        $response = $bodyParser->process($this->createMockRequest($mimeType), $catcher);

        $this->assertSame(Status::OK, $response->getStatusCode());
    }

    public function testWithDefaultBadRequestResponse(): void
    {
        $badResponseHandler = $this->createDefaultBadResponseHandler();
        $bodyParser = $this->getRequestBodyParser($this->getContainer(), $badResponseHandler);

        $rawBody = '{"test": invalid json}';
        $catcher = new RequestCatcher();

        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $catcher);

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame(Status::TEXTS[Status::BAD_REQUEST] . "\n" . 'Invalid JSON data in request body: Syntax error', (string) $response->getBody());
    }

    public function testWithCustomBadRequestResponse(): void
    {
        $customBody = 'custom response';
        $badResponseHandler = $this->createCustomBadResponseHandler($customBody);

        $bodyParser = $this->getRequestBodyParser($this->getContainer(), $badResponseHandler);

        $rawBody = '{"test": invalid json}';
        $catcher = new RequestCatcher();

        $response = $bodyParser->process($this->createMockRequest('application/json', $rawBody), $catcher);

        $this->assertSame(Status::BAD_REQUEST, $response->getStatusCode());
        $this->assertSame($customBody, (string) $response->getBody());
    }

    public function testThrownExceptionWithNotExistsParser(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The parser "invalidParser" cannot be found.');

        $this->getRequestBodyParser($this->getContainer())
            ->withParser('test/test', 'invalidParser');
    }

    public function testThrownExceptionWithInvalidMimeType(): void
    {
        $containerId = 'testParser';
        $container = $this->getContainerWithParser($containerId);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid mime type.');

        $this->getRequestBodyParser($container)
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
        $middleware = $this->getRequestBodyParser($this->getContainer())
            ->withParser($parserType, JsonParser::class);
        $rawBody = '{"test":"value"}';
        $catcher = new RequestCatcher();

        $middleware->process($this->createMockRequest($contentType, $rawBody), $catcher);
        $request = $catcher->getRequest();

        $this->assertSame(['test' => 'value'], $request->getParsedBody());
    }

    public function testRequestWithoutContentType(): void
    {
        $middleware = $this->getRequestBodyParser($this->getContainer());
        $request = new ServerRequest(
            body: (new StreamFactory())->createStream('{"test":"value"}'),
        );
        $catcher = new RequestCatcher();

        $middleware->process($request, $catcher);
        $request = $catcher->getRequest();

        $this->assertSame('{"test":"value"}', (string) $request->getBody());
        $this->assertNull($request->getParsedBody());
    }

    public function testImmutability(): void
    {
        $middleware = $this->getRequestBodyParser($this->getContainer());
        $this->assertNotSame($middleware, $middleware->withoutParsers());
        $this->assertNotSame($middleware, $middleware->withParser('myapp/json', JsonParser::class));
    }

    private function getContainer(): SimpleContainer
    {
        return new SimpleContainer([
            JsonParser::class => new JsonParser(),
        ]);
    }

    private function getContainerWithParser(
        string $id,
        array|object|null $expectedOutput = null,
        bool $throwException = false,
    ): SimpleContainer {
        return new SimpleContainer(
            [
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

    private function createHandler(): RequestHandlerInterface
    {
        $mockResponse = $this->createMock(ResponseInterface::class);
        $mockResponse
            ->method('getStatusCode')
            ->willReturn(Status::OK);

        return new class ($mockResponse) implements RequestHandlerInterface {
            private array|object|null $requestParsedBody = null;

            public function __construct(
                private readonly ResponseInterface $mockResponse,
            ) {
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->requestParsedBody = $request->getParsedBody();
                return $this->mockResponse;
            }

            public function getRequestParsedBody(): array|object|null
            {
                return $this->requestParsedBody;
            }
        };
    }

    private function getRequestBodyParser(
        SimpleContainer $container,
        BadRequestHandlerInterface|null $badRequestHandler = null
    ): RequestBodyParser {
        return new RequestBodyParser($container, $badRequestHandler);
    }

    private function createDefaultBadResponseHandler(): BadRequestHandler
    {
        return new BadRequestHandler(new ResponseFactory());
    }

    private function createCustomBadResponseHandler(string $body): BadRequestHandlerInterface
    {
        return new class ($body, new ResponseFactory()) implements BadRequestHandlerInterface {
            public function __construct(
                private readonly string $body,
                private readonly ResponseFactoryInterface $responseFactory
            ) {
            }

            public function handle(ServerRequestInterface $request, ?ParserException $e = null): ResponseInterface
            {
                $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
                $response->getBody()->write($this->body);
                return $response;
            }
        };
    }
}
