<?php

namespace rikudou\SkQrPayment\Tests;

use PHPUnit\Framework\TestCase;
use rikudou\SkQrPayment\QrPayment;

class IbanConversionTest extends TestCase
{

    public function testAccountsWithoutPrefix() {
        $accounts = array(
            "CZ55 3030 0000 0013 2509 0010" => array(
                "acc" => "1325090010",
                "bank" => "3030",
            ),
            "CZ36 3030 0000 0013 2509 0061" => array(
                "acc" => "1325090061",
                "bank" => "3030",
            ),
            "CZ91 0300 0000 0002 8111 5217" => array(
                "acc" => "281115217",
                "bank" => "0300",
            ),
            "CZ52 0300 0000 0000 0398 3815" => array(
                "acc" => "3983815",
                "bank" => "0300",
            ),
            "CZ13 2700 0000 0005 0011 4004" => array(
                "acc" => "500114004",
                "bank" => "2700",
            ),
        );

        foreach ($accounts as $iban => $accountData) {
            $iban = str_replace(" ", "", $iban);
            $this->assertEquals($iban, $this->getIban($accountData["acc"], $accountData["bank"]));
        }
    }

    public function testAccountsWithPrefix() {
        $accounts = array(
            "CZ03 0710 0010 1100 1792 9051" => array(
                "acc" => "17929051",
                "bank" => "0710",
                "prefix" => "1011"
            ),
            "CZ47 0710 0210 1200 2792 4051" => array(
                "acc" => "27924051",
                "bank" => "0710",
                "prefix" => "21012"
            )
        );

        foreach ($accounts as $iban => $accountData) {
            $iban = str_replace(" ", "", $iban);
            $this->assertEquals($iban, $this->getIban($accountData["acc"], $accountData["bank"], $accountData["prefix"]));
        }
    }

    /**
     * @param string|int $account
     * @param string|int $bankCode
     * @param string|int|null $prefix
     *
     * @return string
     * @throws \rikudou\SkQrPayment\QrPaymentException
     */
    private function getIban($account, $bankCode, $prefix = NULL) {
        if (!is_null($prefix)) {
            $account = "{$prefix}-{$account}";
        }
        $payment = @new QrPayment($account, $bankCode);
        return $payment->setCountry("CZ")->getIBAN();
    }

}
