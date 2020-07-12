<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use Yiisoft\Request\Body\ParserException;
use Yiisoft\Request\Body\ParserInterface;

final class MockParser implements ParserInterface
{
    private $response;
    private bool $throwException;

    public function __construct($response, bool $throwException)
    {
        $this->response = $response;
        $this->throwException = $throwException;
    }

    public function parse(string $rawBody)
    {
        if ($this->throwException) {
            throw new ParserException();
        }

        return $this->response;
    }
}
