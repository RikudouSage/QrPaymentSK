<?php

namespace rikudou\SkQrPayment\Helper;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\CacheException;

trait CacheableBicDictionaryTrait
{
    /**
     * @var array<string, string>
     */
    private $cache = [];

    /**
     * Checks whether the BIC for given IBAN is already cached in memory
     */
    private function isCached(IbanInterface $iban): bool
    {
        return isset($this->cache[$iban->asString()]);
    }

    /**
     * Caches the BIC relevant to given IBAN in memory
     */
    private function cacheResult(IbanInterface $iban, string $bic): void
    {
        $this->cache[$iban->asString()] = $bic;
    }

    /**
     * If the BIC for given IBAN is already cached returns it, otherwise null
     */
    private function getCached(IbanInterface $iban): string
    {
        if (!$this->isCached($iban)) {
            // @codeCoverageIgnoreStart
            throw new CacheException("There is no cache for IBAN {$iban}");
            // @codeCoverageIgnoreEnd
        }

        return $this->cache[$iban->asString()];
    }
}
