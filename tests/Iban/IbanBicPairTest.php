<?php

namespace rikudou\SkQrPayment\Tests\Iban;

use Rikudou\Iban\Iban\CzechIbanAdapter;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Iban\IbanBicPair;
use PHPUnit\Framework\TestCase;

class IbanBicPairTest extends TestCase
{

    private const VALID_STRING_IBAN = 'CZ7061000000001030900063';

    public function testValidStringIbanNoBic()
    {
        $instance = new IbanBicPair(self::VALID_STRING_IBAN);

        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('EQBKCZPP', $instance->getBic());
    }

    public function testValidStringIbanWithBic()
    {
        $instance = new IbanBicPair(self::VALID_STRING_IBAN, 'EQBKCZPP');
        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('EQBKCZPP', $instance->getBic());

        // BIC is not validated, any valid string should be ok
        $instance = new IbanBicPair(self::VALID_STRING_IBAN, 'RANDOM11');
        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('RANDOM11', $instance->getBic());
    }

    public function testValidObjectIbanNoBic()
    {
        $instance = new IbanBicPair(
            new CzechIbanAdapter('1030900063', '6100')
        );
        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('EQBKCZPP', $instance->getBic());
    }

    public function testValidObjectIbanWithBic()
    {
        $instance = new IbanBicPair(
            new IBAN(self::VALID_STRING_IBAN),
            'TEST'
        );
        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('TEST', $instance->getBic());
    }

    public function testValidIbanWithUnknownBic()
    {

    }

}
