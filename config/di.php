<?php

declare(strict_types=1);

use Yiisoft\Definitions\Reference;
use Yiisoft\Request\Body\BadRequestAction;
use Yiisoft\Request\Body\RequestBodyParser;

return [
    RequestBodyParser::class => [
        '__construct()' => [
            'badRequestAction' => Reference::to(BadRequestAction::class),
        ],
    ],
];
