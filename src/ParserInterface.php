<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body;

/**
 * Parser allows to parse raw server request body.
 */
interface ParserInterface
{
    /**
     * Parse raw server request body.
     *
     * @param string $rawBody Raw server request body.
     * @return array|object|null Parsing result.
     *
     * @throws ParserException when parsing can not be done.
     */
    public function parse(string $rawBody);
}
