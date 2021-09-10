<?php

namespace rikudou\SkQrPayment\Iban;

use Rikudou\Iban\Helper\ToStringIbanTrait;
use Rikudou\Iban\Iban\IBAN;
use Rikudou\Iban\Iban\IbanInterface;
use Rikudou\Iban\Validator\ValidatorInterface;
use rikudou\SkQrPayment\Exception\QrPaymentException;
use rikudou\SkQrPayment\IbanToBic\BicLocator;

final class IbanBicPair implements IbanInterface
{
    use ToStringIbanTrait;

    /**
     * @var IbanInterface
     */
    private $iban;

    /**
     * @var string
     */
    private $bic;

    /**
     * @param string|IbanInterface $iban
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
            $bic = (new BicLocator())->getBic($iban);
        }

        $validator = $iban->getValidator();
        if ($validator !== null && !$validator->isValid()) {
            throw new QrPaymentException('The IBAN is not valid');
        }

        $this->iban = $iban;
        $this->bic = $bic;
    }

    public function getIban(): IbanInterface
    {
        return $this->iban;
    }

    public function getBic(): string
    {
        return $this->bic;
    }

    /**
     * Returns the resulting IBAN.
     */
    public function asString(): string
    {
        return $this->getIban()->asString();
    }

    /**
     * Returns the validator that checks whether the IBAN is valid.
     */
    public function getValidator(): ?ValidatorInterface
    {
        return $this->getIban()->getValidator();
    }
}
