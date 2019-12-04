<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\BicNotFoundException;

interface BicDictionaryInterface
{
    /**
     * Returns the two-letter country code this dictionary is relevant to
     *
     * @return string
     */
    public function getCountryCode(): string ;

    /**
     * Returns the BIC, throws BicNotFoundException if the BIC could not be found
     *
     * @param IbanInterface $iban
     *
     * @throws BicNotFoundException
     *
     * @return string
     */
    public function getBic(IbanInterface $iban): string ;
}
