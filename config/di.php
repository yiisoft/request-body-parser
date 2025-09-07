<?php

declare(strict_types=1);

use Yiisoft\Definitions\Reference;
use Yiisoft\Request\Body\BadRequestHandler;
use Yiisoft\Request\Body\RequestBodyParser;

return [
    RequestBodyParser::class => [
        '__construct()' => [
            'badRequestHandler' => Reference::to(BadRequestHandler::class),
        ],
    ],
];
