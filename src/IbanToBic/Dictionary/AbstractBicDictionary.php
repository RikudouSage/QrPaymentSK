<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\BicNotFoundException;
use rikudou\SkQrPayment\Helper\CacheableBicDictionaryTrait;

abstract class AbstractBicDictionary implements BicDictionaryInterface
{
    use CacheableBicDictionaryTrait;

    public function getBic(IbanInterface $iban): string
    {
        if ($this->isCached($iban)) {
            return $this->getCached($iban);
        }

        if (isset($this->getMap()[$this->getBankCode($iban)])) {
            $bic = $this->getMap()[$this->getBankCode($iban)];
            $this->cacheResult($iban, $bic);

            return $bic;
        }

        throw new BicNotFoundException(sprintf(
            'Could not find BIC for IBAN "%s"',
            $iban->asString()
        ));
    }

    /**
     * Returns the bank code map in format 'bankCode' => 'BIC'
     *
     * @return array<string, string>
     */
    abstract protected function getMap(): array;

    private function getBankCode(IbanInterface $iban): string
    {
        return substr($iban, 4, 4);
    }
}
