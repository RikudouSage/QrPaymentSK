<?php

namespace rikudou\SkQrPayment\IbanToBic;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\DictionaryNotFoundException;
use rikudou\SkQrPayment\IbanToBic\Dictionary\CzechBicDictionary;
use rikudou\SkQrPayment\IbanToBic\Dictionary\IbanToBicDictionaryInterface;
use rikudou\SkQrPayment\IbanToBic\Dictionary\SlovakianBicDictionary;

final class IbanToBicConverter
{
    /**
     * @var IbanToBicDictionaryInterface[]
     */
    private $dictionaries = [];

    public function __construct()
    {
        $this->dictionaries = [
            new SlovakianBicDictionary(),
            new CzechBicDictionary(),
        ];
    }

    public function getBic(IbanInterface $iban): string
    {
        $countryCode = $this->getCountryCode($iban);

        foreach ($this->dictionaries as $dictionary) {
            if ($dictionary->getCountryCode() === $countryCode) {
                return $dictionary->getBic($iban);
            }
        }

        throw new DictionaryNotFoundException("Could not find any dictionary for country code '{$countryCode}'");
    }

    private function getCountryCode(IbanInterface $iban): string
    {
        return strtoupper(substr($iban, 0, 2));
    }
}
