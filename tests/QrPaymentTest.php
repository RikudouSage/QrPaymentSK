<?php

namespace rikudou\SkQrPayment\Tests;

use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Payment\QrPaymentOptions;
use rikudou\SkQrPayment\QrPayment;
use PHPUnit\Framework\TestCase;

class QrPaymentTest extends TestCase
{
    private const VALID_IBAN_1 = 'CZ7061000000001030900063';
    private const VALID_IBAN_2 = 'SK6807200002891987426353';

    /**
     * @var QrPayment
     */
    private $instance;

    protected function setUp()
    {
        $this->instance = new QrPayment(new IBAN(self::VALID_IBAN_1), new IBAN(self::VALID_IBAN_2));
    }

    public function testGetIbans()
    {
        $expectedIbans = [
            self::VALID_IBAN_1,
            self::VALID_IBAN_2
        ];

        $ibans = $this->instance->getIbans();
        self::assertCount(2, $ibans);

        foreach ($ibans as $iban) {
            self::assertContains($iban->asString(), $expectedIbans);
        }
    }

    public function testRemoveIban()
    {
        $this->instance->removeIban(new IBAN(self::VALID_IBAN_1));
        self::assertCount(1, $this->instance->getIbans());
        $iban = $this->instance->getIbans()[array_key_first($this->instance->getIbans())];
        self::assertEquals(self::VALID_IBAN_2, $iban->asString());
    }

    public function testSetOptions()
    {

    }

    public function testGetQrString()
    {

    }

    public function testSetXzBinary()
    {

    }

    public function testAddIban()
    {

    }

    public function testGetXzBinary()
    {

    }

    public function testGetQrImage()
    {

    }

    public function testFromIBAN()
    {

    }

    public function testFromIBANs()
    {

    }
}
