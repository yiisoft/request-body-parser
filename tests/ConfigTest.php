<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseFactoryInterface;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Request\Body\BadRequestHandler;
use Yiisoft\Request\Body\BadRequestHandlerInterface;

use function dirname;

final class ConfigTest extends TestCase
{
    public function testBadRequestHandler(): void
    {
        $container = $this->createContainer();

        $requestHandler = $container->get(BadRequestHandlerInterface::class);
        $this->assertInstanceOf(BadRequestHandler::class, $requestHandler);
    }

    private function createContainer(): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions([
                ResponseFactoryInterface::class => Psr17Factory::class,
                ...$this->getContainerDefinitions(),
            ])
        );
    }

    private function getContainerDefinitions(): array
    {
        return require dirname(__DIR__) . '/config/di-web.php';
    }
}
