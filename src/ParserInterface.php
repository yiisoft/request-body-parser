<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

interface ParserInterface
{
    /**
     * @return array|object|null
     *
     * @throws ParserException
     */
    public function parse(string $rawBody);
}
