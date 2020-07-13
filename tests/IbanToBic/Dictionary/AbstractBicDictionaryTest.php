<?php

namespace rikudou\SkQrPayment\Tests\Iban\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\IbanToBic\Dictionary\AbstractBicDictionary;
use PHPUnit\Framework\TestCase;

class AbstractBicDictionaryTest extends TestCase
{
    private const VALID_IBAN = 'CZ7061000000001030900063';

    /**
     * @var AbstractBicDictionary
     */
    private $instance;

    protected function setUp(): void
    {
        $this->instance = new class extends AbstractBicDictionary {

            /**
             * @inheritDoc
             */
            protected function getMap(): array
            {
                return [
                    '6100' => 'EQBKCZPP',
                ];
            }

            /**
             * @inheritDoc
             */
            public function getCountryCode(): string
            {
                return 'CZ';
            }
        };
    }

    public function testGetBic()
    {
        self::assertEquals('EQBKCZPP', $this->instance->getBic(new IBAN(self::VALID_IBAN)));
        // test cache returns the same
        self::assertEquals('EQBKCZPP', $this->instance->getBic(new IBAN(self::VALID_IBAN)));
    }
}
