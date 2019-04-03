<?php

namespace rikudou\SkQrPayment;

/**
 * @final
 */
class IBANtoBIC
{
    private $map = [
    'CZ' => [
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
    ],
    'SK' => [
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
    ],
  ];

    /**
     * @var string
     */
    private $iban;

    private $bic = null;

    /**
     * @param string $iban
     */
    public function __construct($iban)
    {
        $this->iban = $iban;
    }

    /**
     * @return string|null
     */
    public function getBIC()
    {
        $this->findBIC();

        return $this->bic;
    }

    private function findBIC()
    {
        if (is_null($this->bic)) {
            if (isset($this->map[$this->getCountry()])) {
                $map = $this->map[$this->getCountry()];
                if (isset($map[$this->getBankCode()])) {
                    $this->bic = $map[$this->getBankCode()];
                }
            }
        }
    }

    /**
     * @return string
     */
    private function getBankCode()
    {
        return substr($this->iban, 4, 4);
    }

    /**
     * @return string
     */
    private function getCountry()
    {
        return strtoupper(substr($this->iban, 0, 2));
    }
}
