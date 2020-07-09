<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

interface ParserInterface
{
    /**
     * @return array|object|null
     *
     * @throws ParseException
     */
    public function parse(string $rawBody);
}
