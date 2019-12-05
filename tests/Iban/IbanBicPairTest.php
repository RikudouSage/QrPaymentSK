<?php

namespace rikudou\SkQrPayment\Tests\Iban;

use Rikudou\Iban\Iban\CzechIbanAdapter;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Exception\BicNotFoundException;
use rikudou\SkQrPayment\Exception\DictionaryNotFoundException;
use rikudou\SkQrPayment\Exception\QrPaymentException;
use rikudou\SkQrPayment\Iban\IbanBicPair;
use PHPUnit\Framework\TestCase;
use stdClass;

class IbanBicPairTest extends TestCase
{

    private const VALID_STRING_IBAN = 'CZ7061000000001030900063';
    private const VALID_SK_IBAN = 'SK6807200002891987426353';

    public function testValidStringIbanNoBic()
    {
        $instance = new IbanBicPair(self::VALID_STRING_IBAN);

        self::assertEquals(self::VALID_STRING_IBAN, $instance->asString());
        self::assertEquals('EQBKCZPP', $instance->getBic());

        $instance = new IbanBicPair(self::VALID_SK_IBAN);
        self::assertEquals(self::VALID_SK_IBAN, $instance->asString());
        self::assertEquals('NBSBSKBX', $instance->getBic());
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

    public function testValidIbanWithUnknownCountry()
    {
        $this->expectException(DictionaryNotFoundException::class);
        new IbanBicPair('NL55ABNA1756700125');
    }

    public function testValidIbanWithUnknownBank()
    {
        $this->expectException(BicNotFoundException::class);
        new IbanBicPair('CZ0750514167419537656264');
    }

    public function testInvalidIban()
    {
        $this->expectException(QrPaymentException::class);
        new IbanBicPair('CZ7061000000001030900064');
    }

    public function testInvalidObject()
    {
        $this->expectException(QrPaymentException::class);
        new IbanBicPair(new stdClass());
    }

    public function testGetValidator()
    {
        $iban = new IBAN(self::VALID_STRING_IBAN);
        $instance = new IbanBicPair($iban);

        if ($iban->getValidator() === null) {
            self::assertEquals($iban->getValidator(), $instance->getValidator());
        } else {
            self::assertEquals(get_class($iban->getValidator()), get_class($instance->getValidator()));
        }
    }
}
