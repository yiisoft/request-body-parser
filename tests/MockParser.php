<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use Yiisoft\Request\Body\ParserException;
use Yiisoft\Request\Body\ParserInterface;

final class MockParser implements ParserInterface
{
    public function __construct(
        private readonly array|object|null $response,
        private readonly bool $throwException,
    ) {
    }

    public function parse(string $rawBody)
    {
        if ($this->throwException) {
            throw new ParserException();
        }

        return $this->response;
    }
}
