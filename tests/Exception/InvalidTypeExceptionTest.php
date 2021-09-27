<?php

namespace rikudou\SkQrPayment\Tests\Exception;

use rikudou\SkQrPayment\Exception\InvalidTypeException;
use PHPUnit\Framework\TestCase;
use stdClass;

class InvalidTypeExceptionTest extends TestCase
{
    public function testMessage()
    {
        self::assertEquals(
            $this->getExpectedMessage('int', 'string'),
            $this->getMessage('int', '')
        );
        self::assertEquals(
            $this->getExpectedMessage("int' or 'string", 'array'),
            $this->getMessage(['int', 'string'], [])
        );
        self::assertEquals(
            $this->getExpectedMessage('string', 'integer'),
            $this->getMessage('string', 5)
        );
        self::assertEquals(
            $this->getExpectedMessage('5', 'double'),
            $this->getMessage(5, 0.1)
        );
        self::assertEquals(
            $this->getExpectedMessage('string', 'integer'),
            $this->getMessage(new class {
                public function __toString()
                {
                    return 'string';
                }
            }, 5)
        );

        self::assertEquals(
            $this->getExpectedMessage('string', 'stdClass'),
            $this->getMessage('string', new stdClass())
        );
    }

    private function getExpectedMessage(string $expected, string $got): string
    {
        return "Expected '{$expected}', got '{$got}'";
    }

    private function getMessage($expected, $actual): string
    {
        return (new InvalidTypeException($expected, $actual))->getMessage();
    }
}
