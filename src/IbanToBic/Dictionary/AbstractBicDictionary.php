<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\CacheException;

abstract class AbstractBicDictionary implements IbanToBicDictionaryInterface
{
    protected $cache = [];

    /**
     * Checks whether the BIC for given IBAN is already cached in memory
     *
     * @param IbanInterface $iban
     *
     * @return bool
     */
    protected function isCached(IbanInterface $iban): bool
    {
        return isset($this->cache[$iban->asString()]);
    }

    /**
     * Caches the BIC relevant to given IBAN in memory
     *
     * @param IbanInterface $iban
     * @param string        $bic
     */
    protected function cacheResult(IbanInterface $iban, string $bic)
    {
        $this->cache[$iban->asString()] = $bic;
    }

    /**
     * If the BIC for given IBAN is already cached returns it, otherwise null
     *
     * @param IbanInterface $iban
     *
     * @return string
     */
    protected function getCached(IbanInterface $iban): string
    {
        if (!$this->isCached($iban)) {
            throw new CacheException("There is no cache for IBAN {$iban}");
        }

        return $this->cache[$iban->asString()];
    }
}
