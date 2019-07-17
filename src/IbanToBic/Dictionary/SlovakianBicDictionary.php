<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\Exception\BicNotFoundException;

/**
 * @internal
 */
class SlovakianBicDictionary extends AbstractBicDictionary
{
    private $map = [
        '0200' => 'SUBASKBX',
        '0900' => 'GIBASKBX',
        '0720' => 'NBSBSKBX',
        '1100' => 'TATRSKBX',
        '1111' => 'UNCRSKBX',
        '3000' => 'SLZBSKBA',
        '3100' => 'LUBASKBX',
        '5200' => 'OTPVSKBX',
        '5600' => 'KOMASK2X',
        '5900' => 'PRVASKBA',
        '6500' => 'POBNSKBA',
        '7300' => 'INGBSKBX',
        '7500' => 'CEKOSKBX',
        '7930' => 'WUSTSKBA',
        '8020' => 'CRLYSKBX',
        '8050' => 'COBASKBX',
        '8100' => 'KOMBSKBA',
        '8120' => 'BSLOSK22',
        '8130' => 'CITISKBA',
        '8170' => 'KBSPSKBX',
        '8160' => 'EXSKSKBX',
        '8180' => 'SPSRSKBA',
        '8300' => 'HSBCSKBA',
        '8320' => 'JTBPSKBA',
        '8330' => 'FIOZSKBA',
        '8350' => 'ABNASKBX',
        '8360' => 'BREXSKBX',
        '9951' => 'XBRASKB1',
    ];

    /**
     * Returns the two-letter country code this dictionary is relevant to
     *
     * @return string
     */
    public function getCountryCode(): string
    {
        return 'SK';
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
