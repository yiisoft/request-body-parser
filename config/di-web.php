<?php

declare(strict_types=1);

use Yiisoft\Request\Body\BadRequestHandler;
use Yiisoft\Request\Body\BadRequestHandlerInterface;

return [
    BadRequestHandlerInterface::class => BadRequestHandler::class,
];
