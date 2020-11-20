<?php

declare(strict_types=1);

namespace Yiisoft\Request\Body\Tests\Parser;

use PHPUnit\Framework\TestCase;
use Yiisoft\Request\Body\Parser\JsonParser;
use Yiisoft\Request\Body\ParserException;

final class JsonParserTest extends TestCase
{
    public function testScalarDataType(): void
    {
        $parser = new JsonParser();
        $parsed = $parser->parse('true');

        $this->assertNull($parsed);
    }

    public function testWithoutAssoc(): void
    {
        $object = new \stdClass();
        $object->test = 'value';

        $parser = new JsonParser(false);
        $parsed = $parser->parse(json_encode($object));

        $this->assertEquals($object, $parsed);
    }

    public function testThrownException(): void
    {
        $this->expectException(ParserException::class);

        $parser = new JsonParser();
        $parsed = $parser->parse('{"test": invalid json}');

        $this->assertNull($parsed);
    }

    public function testWithoutThrownException(): void
    {
        $parser = new JsonParser(true, 512, JSON_INVALID_UTF8_IGNORE);
        $parsed = $parser->parse('{"test": invalid json}');

        $this->assertNull($parsed);
    }

    public function testIgnoreInvalidUTF8(): void
    {
        $parser = new JsonParser();
        $parsed = $parser->parse('{"test":"value", "invalid":"' . chr(193) . '"}');

        $this->assertSame(['test' => 'value', 'invalid' => ''], $parsed);
    }

    public function testEmptyBody(): void
    {
        $parser = new JsonParser();
        $parsed = $parser->parse('');

        $this->assertNull($parsed);
    }
}
