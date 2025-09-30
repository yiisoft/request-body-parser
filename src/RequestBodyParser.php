<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Yiisoft\Http\Header;
use Yiisoft\Request\Body\Parser\JsonParser;

use function array_key_exists;
use function count;
use function explode;
use function is_array;
use function is_object;
use function str_contains;
use function strtolower;
use function trim;

/**
 * The package is a PSR-15 middleware that allows parsing PSR-7 server request body selecting the parser according
 * to the server request mime type.
 *
 * @see https://www.php-fig.org/psr/psr-7/
 * @see https://www.php-fig.org/psr/psr-15/
 */
final class RequestBodyParser implements MiddlewareInterface
{
    /**
     * @var string[]
     * @psalm-var array<string, string>
     */
    private array $parsers = [
        'application/json' => JsonParser::class,
    ];

    /**
     * @param ContainerInterface $container PSR-11 container to create parsers.
     * @param BadRequestHandlerInterface|null $badRequestHandler Handler for request parsing errors.
     * If set to `null`, the request will continue processing unaltered.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly ?BadRequestHandlerInterface $badRequestHandler
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $parser = $this->getParser($this->getContentType($request));
        if ($parser !== null) {
            try {
                /** @var mixed $parsed */
                $parsed = $parser->parse((string)$request->getBody());
                if ($parsed !== null && !is_object($parsed) && !is_array($parsed)) {
                    $parserClass = $parser::class;
                    throw new RuntimeException(
                        "$parserClass::parse() return value must be an array, an object, or null."
                    );
                }
                $request = $request->withParsedBody($parsed);
            } catch (ParserException $e) {
                if ($this->badRequestHandler !== null) {
                    return $this->badRequestHandler->handle($request, $e);
                }
            }
        }

        return $handler->handle($request);
    }

    /**
     * Registers a request parser for a mime type specified.
     *
     * @param string $mimeType Mime type to register parser for.
     * @param string $parserClass Parser fully qualified name.
     *
     * @return self
     */
    public function withParser(string $mimeType, string $parserClass): self
    {
        $this->validateMimeType($mimeType);
        if ($parserClass === '') {
            throw new InvalidArgumentException('The parser class cannot be an empty string.');
        }

        if ($this->container->has($parserClass) === false) {
            throw new InvalidArgumentException("The parser \"$parserClass\" cannot be found.");
        }

        $new = clone $this;
        $new->parsers[$this->normalizeMimeType($mimeType)] = $parserClass;
        return $new;
    }

    /**
     * Returns new instance with parsers un-registered for mime types specified.
     *
     * @param string ...$mimeTypes Mime types to unregister parsers for.
     *
     * @return self
     */
    public function withoutParsers(string ...$mimeTypes): self
    {
        $new = clone $this;
        if (count($mimeTypes) === 0) {
            $new->parsers = [];
            return $new;
        }
        foreach ($mimeTypes as $mimeType) {
            $this->validateMimeType($mimeType);
            unset($new->parsers[$this->normalizeMimeType($mimeType)]);
        }
        return $new;
    }

    /**
     * @psalm-suppress MixedInferredReturnType, MixedReturnStatement
     */
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
        if (trim($contentType) !== '') {
            if (str_contains($contentType, ';')) {
                $contentTypeParts = explode(';', $contentType, 2);
                return strtolower(trim($contentTypeParts[0]));
            }
            return strtolower(trim($contentType));
        }
        return null;
    }

    /**
     * @throws InvalidArgumentException
     */
    private function validateMimeType(string $mimeType): void
    {
        if (!str_contains($mimeType, '/')) {
            throw new InvalidArgumentException('Invalid mime type.');
        }
    }

    private function normalizeMimeType(string $mimeType): string
    {
        return strtolower(trim($mimeType));
    }
}
