<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://github.com/yiisoft.png" height="100px">
    </a>
    <h1 align="center">Yii Request Body Parser</h1>
    <br>
</p>

The package is [PSR-15](https://www.php-fig.org/psr/psr-15/) middleware that allows parsing [PSR-7](https://www.php-fig.org/psr/psr-7/)
server request body selecting the parser according to server request mime type.

[![Latest Stable Version](https://poser.pugx.org/yiisoft/request-body-parser/v/stable.png)](https://packagist.org/packages/yiisoft/request-body-parser)
[![Total Downloads](https://poser.pugx.org/yiisoft/request-body-parser/downloads.png)](https://packagist.org/packages/yiisoft/request-body-parser)
[![Build status](https://github.com/yiisoft/request-body-parser/workflows/build/badge.svg)](https://github.com/yiisoft/request-body-parser/actions?query=workflow%3Abuild))
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/request-body-parser/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/request-body-parser/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/request-body-parser/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/request-body-parser/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Frequest-body-parser%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/request-body-parser/master)
[![static analysis](https://github.com/yiisoft/request-body-parser/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/request-body-parser/actions?query=workflow%3A%22static+analysis%22)

## General usage

1. Add `RequestBodyParser` into your middleware stack.
2. Obtain parsed body via `$request->getParsedBody();`.

By default, it parses `application/json` requests where JSON is in the body. 

You can add your own parser by implementing `ParserInterface`, adding it into the container and registering it within
the middleware:

```php
$requestBodyParser = $requestBodyParser->withAddedParser('application/myformat', MyFormatParser::class);
``` 

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```php
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```php
./vendor/bin/infection
```

### Static analysis

The code is statically analyzed with [Phan](https://github.com/phan/phan/wiki). To run static analysis:

```php
./vendor/bin/phan
```
