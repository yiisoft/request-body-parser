<?php


namespace Yiisoft\Request\Body;


use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

interface BadRequestHandlerInterface extends RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface;
    public function withParserException(ParserException $e): BadRequestHandlerInterface;
}
