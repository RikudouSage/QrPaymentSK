<?php

namespace rikudou\SkQrPayment;

use DateTime;
use DateTimeInterface;
use Endroid\QrCode\QrCode;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use Rikudou\Iban\Iban\IbanInterface;
use Rikudou\QrPayment\QrPaymentInterface;
use Rikudou\QrPaymentQrCodeProvider\EndroidQrCode3;
use Rikudou\QrPaymentQrCodeProvider\Exception\NoProviderFoundException;
use Rikudou\QrPaymentQrCodeProvider\GetQrCodeTrait;
use rikudou\SkQrPayment\Exception\InvalidTypeException;
use rikudou\SkQrPayment\Exception\QrPaymentException;
use rikudou\SkQrPayment\Iban\IbanBicPair;
use rikudou\SkQrPayment\Payment\QrPaymentOptions;
use rikudou\SkQrPayment\Xz\XzBinaryLocator;
use rikudou\SkQrPayment\Xz\XzBinaryLocatorInterface;
use TypeError;

final class QrPayment implements QrPaymentInterface
{
    use GetQrCodeTrait;

    /**
     * @var IbanInterface[]
     */
    private $ibans = [];

    /**
     * @var int|string|null
     */
    private $variableSymbol = null;

    /**
     * @var int|string|null
     */
    private $specificSymbol = null;

    /**
     * @var int|string|null
     */
    private $constantSymbol = null;

    /**
     * @var string
     */
    private $currency = 'EUR';

    /**
     * @var string
     */
    private $comment = '';

    /**
     * @var string
     */
    private $internalId = '';

    /**
     * @var DateTimeInterface|null
     */
    private $dueDate = null;

    /**
     * @var float
     */
    private $amount = 0;

    /**
     * @var string
     */
    private $country = 'SK';

    /**
     * @var string
     */
    private $payeeName = '';

    /**
     * @var string
     */
    private $payeeAddressLine1 = '';

    /**
     * @var string
     */
    private $payeeAddressLine2 = '';

    /**
     * @var XzBinaryLocatorInterface
     */
    private $xzBinaryLocator;

    /**
     * @param IbanInterface ...$ibans
     */
    public function __construct(IbanInterface ...$ibans)
    {
        $this->setIbans($ibans);
        $this->xzBinaryLocator = new XzBinaryLocator(null);
    }

    /**
     * Specifies options in format:
     * property_name => value
     *
     * @param array<string, mixed> $options
     *
     * @see QrPaymentOptions
     */
    public function setOptions(array $options): self
    {
        foreach ($options as $key => $value) {
            $method = sprintf('set%s', ucfirst($key));
            if (method_exists($this, $method)) {
                /** @var callable $callable */
                $callable = [$this, $method];
                call_user_func($callable, $value);
            } else {
                throw new InvalidArgumentException("The property '{$key}' is not valid");
            }
        }

        return $this;
    }

    /**
     * @throws QrPaymentException
     */
    public function getQrString(): string
    {
        if (!count($this->ibans)) {
            throw new QrPaymentException('Cannot generate QR payment with no IBANs');
        }

        $ibans = $this->getNormalizedIbans();

        $dataArray = [
            0 => $this->internalId, // payment identifier (can be anything)
            1 => '1', // count of payments
            2 => [
                true, // regular payment
                round($this->amount, 2),
                $this->currency,
                $this->getDueDate()->format('Ymd'),
                $this->variableSymbol,
                $this->constantSymbol,
                $this->specificSymbol,
                '', // variable symbol, constant symbol and specific symbol in SEPA format (empty because the 3 previous are already defined)
                $this->comment,
                count($this->ibans), // count of target accounts
                // continues below in foreach
            ],
        ];

        foreach ($ibans as $iban) { // each of the ibans is appended, then the bic
            $dataArray[2][] = $iban->getIban()->asString();
            $dataArray[2][] = $iban->getBic();
        }

        $dataArray[2][] = 0; // standing order
        $dataArray[2][] = 0; // direct debit
        $dataArray[2][] = $this->payeeName;
        $dataArray[2][] = $this->payeeAddressLine1;
        $dataArray[2][] = $this->payeeAddressLine2;

        $dataArray[2] = implode("\t", $dataArray[2]);

        $data = implode("\t", $dataArray);

        // get the crc32 of the string in binary format and prepend it to the data
        $hashedData = strrev(hash('crc32b', $data, true)) . $data;
        $xzBinary = $this->getXzBinary();

        // we need to get raw lzma1 compressed data with parameters LC=3, LP=0, PB=2, DICT_SIZE=128KiB
        $xzProcess = proc_open("{$xzBinary} --format=raw --lzma1=lc=3,lp=0,pb=2,dict=128KiB -c -", [
            0 => [
                'pipe',
                'r',
            ],
            1 => [
                'pipe',
                'w',
            ],
        ], $xzProcessPipes);
        assert(is_resource($xzProcess));

        fwrite($xzProcessPipes[0], $hashedData);
        fclose($xzProcessPipes[0]);

        $pipeOutput = stream_get_contents($xzProcessPipes[1]);
        fclose($xzProcessPipes[1]);
        proc_close($xzProcess);

        // we need to strip the EOF data and prepend 4 bytes of data, first 2 bytes define document type, the other 2
        // define the length of original string, all the magic below does that
        $hashedData = bin2hex("\x00\x00" . pack('v', strlen($hashedData)) . $pipeOutput);

        $base64Data = '';
        for ($i = 0; $i < strlen($hashedData); $i++) {
            $base64Data .= str_pad(base_convert($hashedData[$i], 16, 2), 4, '0', STR_PAD_LEFT);
        }

        $length = strlen($base64Data);

        $controlDigit = $length % 5;
        if ($controlDigit > 0) {
            $count = 5 - $controlDigit;
            $base64Data .= str_repeat('0', $count);
            $length += $count;
        }

        $length = $length / 5;
        assert(is_int($length));

        $hashedData = str_repeat('_', $length);

        // convert the resulting binary data (5 bits at a time) according to table from specification
        for ($i = 0; $i < $length; $i++) {
            $hashedData[$i] = '0123456789ABCDEFGHIJKLMNOPQRSTUV'[bindec(substr($base64Data, $i * 5, 5))];
        }

        // and that's it, this totally-not-crazy-overkill-format-that-allows-you-to-sell-your-proprietary-solution
        // process is done
        return $hashedData;
    }

    /**
     * Return QrCode object with QrString set, for more info see Endroid QrCode
     * documentation
     *
     * @throws QrPaymentException
     */
    #[Deprecated('This method has been deprecated, please use getQrCode()', '%class%->getQrCode()->getRawObject()')]
    public function getQrImage(): QrCode
    {
        try {
            $code = $this->getQrCode();
            if (!$code instanceof EndroidQrCode3) {
                throw new QrPaymentException('Error: library endroid/qr-code is not loaded or is not a 3.x version. For newer versions please use method getQrCode()');
            }
            // @codeCoverageIgnoreStart
        } catch (NoProviderFoundException $e) {
            throw new QrPaymentException('Error: library endroid/qr-code is not loaded.');
            // @codeCoverageIgnoreEnd
        }

        $raw = $code->getRawObject();
        assert($raw instanceof QrCode);

        return $raw;
    }

    /**
     * @param string|IbanInterface $iban
     */
    public static function fromIBAN($iban): self
    {
        if (is_string($iban)) {
            $iban = new IbanBicPair($iban);
        } elseif (!$iban instanceof IbanInterface) {
            throw new InvalidTypeException([
                'string',
                IbanInterface::class,
            ], $iban);
        }

        return new self($iban);
    }

    public function addIban(IbanInterface $iban): self
    {
        if (!isset($this->ibans[$iban->asString()])) {
            $this->ibans[$iban->asString()] = $iban;
        }

        return $this;
    }

    public function removeIban(IbanInterface $iban): self
    {
        if (isset($this->ibans[$iban->asString()])) {
            unset($this->ibans[$iban->asString()]);
        }

        return $this;
    }

    /**
     * @return IbanInterface[]
     */
    public function getIbans(): array
    {
        return $this->ibans;
    }

    /**
     * @param IbanInterface[] $ibans
     */
    public function setIbans(array $ibans): self
    {
        foreach ($this->ibans as $iban) {
            $this->removeIban($iban);
        }
        foreach ($ibans as $iban) {
            $this->addIban($iban);
        }

        return $this;
    }

    /**
     * @param int|string|null $variableSymbol
     */
    public function setVariableSymbol($variableSymbol): self
    {
        if (is_object($variableSymbol) && method_exists($variableSymbol, '__toString')) {
            $variableSymbol = (string) $variableSymbol;
        }
        if (!is_string($variableSymbol) && !is_int($variableSymbol) && $variableSymbol !== null) {
            throw new TypeError(sprintf(
                'Argument 1 passed to %s must be of the type string|int|null, %s given',
                __METHOD__,
                gettype($variableSymbol)
            ));
        }
        $this->variableSymbol = $variableSymbol;

        return $this;
    }

    /**
     * @param int|string|null $specificSymbol
     */
    public function setSpecificSymbol($specificSymbol): self
    {
        if (is_object($specificSymbol) && method_exists($specificSymbol, '__toString')) {
            $specificSymbol = (string) $specificSymbol;
        }
        if (!is_string($specificSymbol) && !is_int($specificSymbol) && $specificSymbol !== null) {
            throw new TypeError(sprintf(
                'Argument 1 passed to %s must be of the type string|int|null, %s given',
                __METHOD__,
                gettype($specificSymbol)
            ));
        }
        $this->specificSymbol = $specificSymbol;

        return $this;
    }

    /**
     * @param int|string|null $constantSymbol
     */
    public function setConstantSymbol($constantSymbol): self
    {
        if (is_object($constantSymbol) && method_exists($constantSymbol, '__toString')) {
            $constantSymbol = (string) $constantSymbol;
        }
        if (!is_string($constantSymbol) && !is_int($constantSymbol) && $constantSymbol !== null) {
            throw new TypeError(sprintf(
                'Argument 1 passed to %s must be of the type string|int|null, %s given',
                __METHOD__,
                gettype($constantSymbol)
            ));
        }
        $this->constantSymbol = $constantSymbol;

        return $this;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    public function setInternalId(string $internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function setDueDate(?DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function setPayeeName(string $payeeName): QrPayment
    {
        $this->payeeName = $payeeName;

        return $this;
    }

    public function setPayeeAddressLine1(string $addressLine): QrPayment
    {
        $this->payeeAddressLine1 = $addressLine;

        return $this;
    }

    public function setPayeeAddressLine2(string $addressLine): QrPayment
    {
        $this->payeeAddressLine2 = $addressLine;

        return $this;
    }

    public function getPayeeAddressLine1(): string
    {
        return $this->payeeAddressLine1;
    }

    public function getPayeeAddressLine2(): string
    {
        return $this->payeeAddressLine2;
    }

    public function getXzBinaryLocator(): XzBinaryLocatorInterface
    {
        return $this->xzBinaryLocator;
    }

    public function setXzBinaryLocator(XzBinaryLocatorInterface $xzBinaryLocator): QrPayment
    {
        $this->xzBinaryLocator = $xzBinaryLocator;

        return $this;
    }

    public function setXzBinary(?string $binaryPath): self
    {
        $this->xzBinaryLocator = new XzBinaryLocator($binaryPath);

        return $this;
    }

    public function getXzBinary(): string
    {
        return $this->xzBinaryLocator->getXzBinary();
    }

    /**
     * @return int|string|null
     */
    public function getVariableSymbol()
    {
        return $this->variableSymbol;
    }

    /**
     * @return int|string|null
     */
    public function getSpecificSymbol()
    {
        return $this->specificSymbol;
    }

    /**
     * @return int|string|null
     */
    public function getConstantSymbol()
    {
        return $this->constantSymbol;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getComment(): string
    {
        return $this->comment;
    }

    public function getInternalId(): string
    {
        return $this->internalId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function getPayeeName(): string
    {
        return $this->payeeName;
    }

    /**
     * Checks whether the due date is set.
     * Throws exception if the date format cannot be parsed by strtotime() func
     */
    public function getDueDate(): DateTimeInterface
    {
        if ($this->dueDate === null) {
            return new DateTime();
        }

        return $this->dueDate;
    }

    /**
     * @return IbanBicPair[]
     */
    private function getNormalizedIbans(): array
    {
        $result = [];
        foreach ($this->ibans as $iban) {
            if (!$iban instanceof IbanBicPair) {
                $result[] = new IbanBicPair($iban);
            } else {
                $result[] = $iban;
            }
        }

        return $result;
    }
}
