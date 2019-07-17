<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\BicNotFoundException;

/**
 * @internal
 */
final class CzechBicDictionary extends AbstractBicDictionary
{
    /**
     * @var string[]
     */
    private $map = [
        '0100' => 'KOMBCZPP',
        '0300' => 'CEKOCZPP',
        '0600' => 'AGBACZPP',
        '0710' => 'CNBACZPP',
        '0800' => 'GIBACZPX',
        '2010' => 'FIOBCZPP',
        '2020' => 'BOTKCZPP',
        '2060' => 'CITFCZPP',
        '2070' => 'MPUBCZPP',
        '2220' => 'ARTTCZPP',
        '2240' => 'POBNCZPP',
        '2250' => 'CTASCZ22',
        '2310' => 'ZUNOCZPP',
        '2600' => 'CITICZPX',
        '2700' => 'BACXCZPP',
        '3030' => 'AIRACZPP',
        '3050' => 'BPPFCZP1',
        '3060' => 'BPKOCZPP',
        '3500' => 'INGBCZPP',
        '4000' => 'SOLACZPP',
        '4300' => 'CMZRCZP1',
        '5500' => 'RZBCCZPP',
        '5800' => 'JTBPCZPP',
        '6000' => 'PMBPCZPP',
        '6100' => 'EQBKCZPP',
        '6200' => 'COBACZPX',
        '6210' => 'BREXCZPP',
        '6300' => 'GEBACZPP',
        '6700' => 'SUBACZPP',
        '6800' => 'VBOECZ2X',
        '7910' => 'DEUTCZPX',
        '7940' => 'SPWTCZ21',
        '8030' => 'GENOCZ21',
        '8040' => 'OBKLCZ2X',
        '8090' => 'CZEECZPP',
        '8150' => 'MIDLCZPP',
        '8220' => 'PAERCZP1',
        '8230' => 'EEPSCZPP',
        '8250' => 'BKCHCZPP',
    ];

    /**
     * Returns the two-letter country code this dictionary is relevant to
     *
     * @return string
     */
    public function getCountryCode(): string
    {
        return 'CZ';
    }

    /**
     * Returns the BIC, throws BicNotFoundException if the BIC could not be found
     *
     * @param IbanInterface $iban
     *
     * @throws BicNotFoundException
     *
     * @return string
     *
     */
    public function getBic(IbanInterface $iban): string
    {
        if ($this->isCached($iban)) {
            return $this->getCached($iban);
        }

        if (isset($this->map[$this->getBankCode($iban)])) {
            $bic = $this->map[$this->getBankCode($iban)];
            $this->cacheResult($iban, $bic);

            return $bic;
        }

        throw new BicNotFoundException(sprintf(
            'Could not find BIC for IBAN "%s"',
            $iban->asString()
        ));
    }

    private function getBankCode(IbanInterface $iban): string
    {
        return substr($iban, 4, 4);
    }
}
