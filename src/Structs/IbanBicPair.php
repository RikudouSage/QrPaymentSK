<?php

namespace rikudou\SkQrPayment\Structs;

use Rikudou\Iban\Iban\IBAN;
use Rikudou\Iban\Iban\IbanInterface;
use rikudou\SkQrPayment\IBANtoBIC;
use rikudou\SkQrPayment\QrPaymentException;

class IbanBicPair
{
    /**
     * @var IbanInterface
     */
    private $iban;

    /**
     * @var string
     */
    private $bic;

    /**
     * BicIbanPair constructor.
     *
     * @param string|IbanInterface $iban
     * @param string|null          $bic
     *
     * @throws QrPaymentException
     */
    public function __construct($iban, ?string $bic = null)
    {
        if (is_string($iban)) {
            $iban = new IBAN($iban);
        } elseif (!$iban instanceof IbanInterface) {
            throw new QrPaymentException(sprintf(
                'The IBAN must be a string or instance of %s',
                IbanInterface::class
            ));
        }
        if ($bic === null) {
            $bic = (new IBANtoBIC($iban))->getBIC();
            if ($bic === null) {
                throw new QrPaymentException(
                    sprintf('Could not find the BIC code for IBAN %s, please supply it manually', $iban)
                );
            }
        }

        $validator = $iban->getValidator();
        if ($validator !== null && !$validator->isValid()) {
            throw new QrPaymentException('The IBAN is not valid');
        }

        $this->iban = $iban;
        $this->bic = $bic;
    }

    /**
     * @return IbanInterface
     */
    public function getIban(): IbanInterface
    {
        return $this->iban;
    }

    /**
     * @return string
     */
    public function getBic(): string
    {
        return $this->bic;
    }
}
