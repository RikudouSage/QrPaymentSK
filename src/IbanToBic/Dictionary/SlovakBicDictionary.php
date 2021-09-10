<?php

namespace rikudou\SkQrPayment\IbanToBic\Dictionary;

/**
 * @internal
 */
final class SlovakBicDictionary extends AbstractBicDictionary
{
    /**
     * Returns the two-letter country code this dictionary is relevant to
     */
    public function getCountryCode(): string
    {
        return 'SK';
    }

    protected function getMap(): array
    {
        return [
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
    }
}
