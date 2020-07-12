<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Http\Header;
use Yiisoft\Http\Status;
use Yiisoft\Request\Body\Parser\JsonParser;

final class RequestBodyParser implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;
    private ContainerInterface $container;

    private array $parsers = [
        'application/json' => JsonParser::class,
    ];
    private bool $badRequestResponse = true;

    public function __construct(ResponseFactoryInterface $responseFactory, ContainerInterface $container)
    {
        $this->responseFactory = $responseFactory;
        $this->container = $container;
    }

    public function withAddedParser(string $mimeType, string $parserClass): self
    {
        if ($mimeType === '') {
            throw new \InvalidArgumentException('The mime type cannot be an empty string!');
        }
        if ($parserClass === '') {
            throw new \InvalidArgumentException('The parser class cannot be an empty string!');
        }
        if (strpos($mimeType, '/') === false) {
            throw new \InvalidArgumentException('Invalid mime type!');
        }
        $new = clone $this;
        $new->parsers[strtolower($mimeType)] = $parserClass;
        return $new;
    }

    public function withoutParsers(string ...$mimeTypes): self
    {
        $new = clone $this;
        if (count($mimeTypes) === 0) {
            $new->parsers = [];
            return $new;
        }
        foreach ($mimeTypes as $mimeType) {
            if (trim($mimeType) === '') {
                throw new \InvalidArgumentException('The mime type cannot be an empty string.');
            }
            unset($new->parsers[strtolower($mimeType)]);
        }
        return $new;
    }

    public function withBadRequestResponse(): self
    {
        $new = clone $this;
        $new->badRequestResponse = true;
        return $new;
    }

    public function withoutBadRequestResponse(): self
    {
        $new = clone $this;
        $new->badRequestResponse = false;
        return $new;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $this->getContentType($request);

        if ($contentType && ($parser = $this->getParser($contentType)) !== null) {
            try {
                $parsed = $parser->parse((string)$request->getBody());
                if (!is_null($parsed) && !is_object($parsed) && !is_array($parsed)) {
                    throw new \RuntimeException(
                        'Request body media type parser return value must be an array, an object, or null'
                    );
                }
                $request = $request->withParsedBody($parsed);
            } catch (ParseException $e) {
                if ($this->badRequestResponse) {
                    $response = $this->responseFactory->createResponse(Status::BAD_REQUEST);
                    $response->getBody()->write(Status::TEXTS[Status::BAD_REQUEST]);
                    return $response;
                }
            }
        }

        return $handler->handle($request);
    }

    private function getParser(string $contentType): ?ParserInterface
    {
        if (isset($this->parsers[$contentType])) {
            return $this->container->get($this->parsers[$contentType]);
        }
        return null;
    }

    private function getContentType(ServerRequestInterface $request): ?string
    {
        $contentType = $request->getHeaderLine(Header::CONTENT_TYPE);
        if (is_string($contentType) && trim($contentType) !== '') {
            if (str_contains($contentType, ';')) {
                $contentTypeParts = explode(';', $contentType, 2);
                return strtolower(trim($contentTypeParts[0]));
            }

            return strtolower($contentType);
        }
        return null;
    }
}
