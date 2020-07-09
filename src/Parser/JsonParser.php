<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Parser;

use Yiisoft\Request\Body\ParseException;
use Yiisoft\Request\Body\ParserInterface;

final class JsonParser implements ParserInterface
{
    private bool $assoc;
    private int $depth;
    private int $options;

    public function __construct(
        bool $assoc = true,
        int $depth = 512,
        int $options = JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_IGNORE
    ) {
        $this->assoc = $assoc;
        $this->depth = $depth;
        $this->options = $options;
    }

    public function parse(string $rawBody)
    {
        try {
            $result = \json_decode($rawBody, $this->assoc, $this->depth, $this->options);
            if (\is_array($result) || \is_object($result)) {
                return $result;
            }
        } catch (\JsonException $e) {
            throw new ParseException('Invalid JSON data in request body: ' . $e->getMessage());
        }

        return null;
    }
}
