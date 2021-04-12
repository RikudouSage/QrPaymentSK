<?php

namespace rikudou\SkQrPayment\Tests;

use DateTime;
use DateTimeInterface;
use Endroid\QrCode\QrCode;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Exception\InvalidTypeException;
use rikudou\SkQrPayment\Exception\QrPaymentException;
use rikudou\SkQrPayment\Payment\QrPaymentOptions;
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Xz\XzBinaryLocator;
use TypeError;

final class QrPaymentTest extends TestCase
{
    private const VALID_IBAN_1 = 'CZ7061000000001030900063';
    private const VALID_IBAN_2 = 'SK6807200002891987426353';

    /**
     * @var QrPayment
     */
    private $instance;

    protected function setUp(): void
    {
        $this->instance = new QrPayment(new IBAN(self::VALID_IBAN_1), new IBAN(self::VALID_IBAN_2));
    }

    public function testGetIbans()
    {
        $expectedIbans = [
            self::VALID_IBAN_1,
            self::VALID_IBAN_2,
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
        $this->instance->setOptions([
            QrPaymentOptions::XZ_PATH => '/tmp',
            QrPaymentOptions::IBANS => [
                new IBAN(self::VALID_IBAN_2),
                new IBAN(self::VALID_IBAN_1),
            ],
            QrPaymentOptions::VARIABLE_SYMBOL => 1,
            QrPaymentOptions::SPECIFIC_SYMBOL => 2,
            QrPaymentOptions::CONSTANT_SYMBOL => 3,
            QrPaymentOptions::CURRENCY => 'CZK',
            QrPaymentOptions::COMMENT => 'test',
            QrPaymentOptions::INTERNAL_ID => '123',
            QrPaymentOptions::DUE_DATE => new DateTime('2019-01-01'),
            QrPaymentOptions::AMOUNT => 100,
            QrPaymentOptions::COUNTRY => 'CZ',
            QrPaymentOptions::PAYEE_NAME => 'Random Dude',
        ]);

        self::assertEquals('/tmp', $this->instance->getXzBinary());
        self::assertCount(2, $this->instance->getIbans());
        self::assertEquals(1, $this->instance->getVariableSymbol());
        self::assertEquals(2, $this->instance->getSpecificSymbol());
        self::assertEquals(3, $this->instance->getConstantSymbol());
        self::assertEquals('CZK', $this->instance->getCurrency());
        self::assertEquals('test', $this->instance->getComment());
        self::assertEquals('123', $this->instance->getInternalId());
        self::assertEquals(
            (new DateTime('2019-01-01'))->format('c'),
            $this->instance->getDueDate()->format('c')
        );
        self::assertEquals(100, $this->instance->getAmount());
        self::assertEquals('CZ', $this->instance->getCountry());
        self::assertEquals('Random Dude', $this->instance->getPayeeName());

        // test that values from array are type checked
        $this->expectException(TypeError::class);
        $this->instance->setOptions([
            QrPaymentOptions::VARIABLE_SYMBOL => 'test',
        ]);
    }

    public function testSetOptionsInvalidKey()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->instance->setOptions([
            'randomKey' => 'randomValue',
        ]);
    }

    public function testGetQrString()
    {
        $expected = '0005C000A2Q0DJ3G9BRS6QPDH5ULN7B0P2AVGBL62AVG88CDE4MG3UNGQFUHGD4SU6VMJ9K6R55NE4DFT7O7V34VRBK0O2ACSV3ITLKU6GT41BNTAOQC26HR0IAQF9EPMDFVVEPRO000';
        $instance = QrPayment::fromIBAN(self::VALID_IBAN_2)
            ->setDueDate(new DateTime('2019-12-01'))
            ->setVariableSymbol(100)
            ->setConstantSymbol(200)
            ->setSpecificSymbol(300)
            ->setPayeeName('No one')
            ->setAmount(1);

        self::assertEquals($expected, $instance->getQrString());
    }

    public function testGetQrStringNoIbans()
    {
        $instance = new QrPayment();
        $this->expectException(QrPaymentException::class);
        $instance->getQrString();
    }

    public function testAddIban()
    {
        $newIban = new IBAN('SK3302000000000000012351');
        $this->instance->addIban($newIban);
        self::assertCount(3, $this->instance->getIbans());
        self::assertContains($newIban, $this->instance->getIbans());

        // test that one IBAN is not added twice
        $this->instance->addIban(new IBAN(self::VALID_IBAN_1));
        self::assertCount(3, $this->instance->getIbans());
    }

    public function testGetXzBinaryAutomatic()
    {
        exec('which xz', $xz, $exitCode);
        if ($exitCode !== 0) {
            self::markTestSkipped('The xz binary not found, skipping test');

            return;
        }
        $xz = $xz[0];

        self::assertEquals($xz, $this->instance->getXzBinary());
    }

    public function testGetXzBinaryManual()
    {
        $this->instance->setXzBinary('/tmp');
        self::assertEquals('/tmp', $this->instance->getXzBinary());
    }

    public function testGetXzBinaryNonexistentPath()
    {
        $this->instance->setXzBinary('/tmp/path/that/hopefully/doesnt/exist');
        $this->expectException(QrPaymentException::class);
        $this->instance->getXzBinary();
    }

    public function testGetDueDate()
    {
        self::assertInstanceOf(DateTimeInterface::class, $this->instance->getDueDate());
        $date = new DateTime();
        $this->instance->setDueDate($date);
        self::assertEquals($date, $this->instance->getDueDate());
    }

    public function testGetQrImage()
    {
        if (!class_exists(QrCode::class)) {
            self::markTestSkipped('The QR code class does not exist, cannot test');

            return;
        }
        self::assertInstanceOf(QrCode::class, $this->instance->getQrImage());
    }

    public function testGetQrImageMissing()
    {
        if (class_exists(QrCode::class)) {
            self::markTestSkipped('The QR code class exists, cannot test for exception');

            return;
        }
        $this->expectException(QrPaymentException::class);
        $this->instance->getQrImage();
    }

    public function testFromIBAN()
    {
        $instance = QrPayment::fromIBAN(self::VALID_IBAN_1);
        self::assertCount(1, $instance->getIbans());

        $instance = QrPayment::fromIBAN(new IBAN(self::VALID_IBAN_1));
        self::assertCount(1, $instance->getIbans());

        $this->expectException(InvalidTypeException::class);
        QrPayment::fromIBAN(123);
    }

    public function testXzBinaryLocator()
    {
        $locator = new XzBinaryLocator(null);
        $this->instance->setXzBinaryLocator($locator);
        self::assertTrue($locator === $this->instance->getXzBinaryLocator());
    }
}
