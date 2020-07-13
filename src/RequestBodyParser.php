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
    private RequestHandlerInterface $badRequestHandler;
    private array $parsers = [
        'application/json' => JsonParser::class,
    ];
    private bool $ignoreBadRequestBody = false;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ContainerInterface $container,
        RequestHandlerInterface $badRequestHandler = null
    ) {
        $this->responseFactory = $responseFactory;
        $this->container = $container;
        $this->badRequestHandler = $badRequestHandler ?? new BadRequestHandler(
                $container->get(ResponseFactoryInterface::class)
            );
    }

    public function withParser(string $mimeType, string $parserClass): self
    {
        if (trim($mimeType) === '') {
            throw new \InvalidArgumentException('The mime type cannot be an empty string.');
        }
        if ($parserClass === '') {
            throw new \InvalidArgumentException('The parser class cannot be an empty string.');
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

    public function ignoreBadRequestBody(): self
    {
        $new = clone $this;
        $new->ignoreBadRequestBody = true;
        return $new;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $parser = $this->getParser($this->getContentType($request));
        if ($parser !== null) {
            try {
                $parsed = $parser->parse((string)$request->getBody());
                if ($parsed !== null && !is_object($parsed) && !is_array($parsed)) {
                    $parserClass = get_class($parser);
                    throw new \RuntimeException(
                        "$parserClass::parse() return value must be an array, an object, or null."
                    );
                }
                $request = $request->withParsedBody($parsed);
            } catch (ParserException $e) {
                if (!$this->ignoreBadRequestBody) {
                    return $this->badRequestHandler->handle($request);
                }
            }
        }

        return $handler->handle($request);
    }

    private function getParser(?string $contentType): ?ParserInterface
    {
        if ($contentType !== null && array_key_exists($contentType, $this->parsers)) {
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
            return strtolower(trim($contentType));
        }
        return null;
    }
}
