<?php

namespace rikudou\SkQrPayment;

use DateTime;
use DateTimeInterface;
use Endroid\QrCode\QrCode;
use InvalidArgumentException;
use Rikudou\Iban\Iban\IbanInterface;
use Rikudou\QrPayment\QrPaymentInterface;
use rikudou\SkQrPayment\Exception\InvalidTypeException;
use rikudou\SkQrPayment\Exception\QrPaymentException;
use rikudou\SkQrPayment\Iban\IbanBicPair;
use rikudou\SkQrPayment\Xz\LinuxXzBinaryLocator;
use rikudou\SkQrPayment\Xz\WindowsXzBinaryLocator;
use rikudou\SkQrPayment\Xz\XzBinaryLocatorInterface;

final class QrPayment implements QrPaymentInterface
{
    /**
     * @var IbanInterface[]
     */
    private $ibans = [];

    /**
     * @var int|null
     */
    private $variableSymbol = null;

    /**
     * @var int|null
     */
    private $specificSymbol = null;

    /**
     * @var int|null
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
     * @var XzBinaryLocatorInterface
     */
    private $xzBinaryLocator;

    /**
     * QrPayment constructor.
     *
     * @param IbanInterface ...$ibans
     */
    public function __construct(IbanInterface ...$ibans)
    {
        $this->setIbans($ibans);
        $this->xzBinaryLocator = $this->getXzBinaryLocatorByOs(null);
    }

    /**
     * Specifies options in array in format:
     * property_name => value
     *
     * @param array<string,mixed> $options
     *
     * @return $this
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
     *
     * @return string
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
        $dataArray[2][] = ''; // payee's address line 1
        $dataArray[2][] = ''; // payee's address line 2

        $dataArray[2] = implode("\t", $dataArray[2]);

        $data = implode("\t", $dataArray);

        // get the crc32 of the string in binary format and prepend it to the data
        $hashedData = strrev(hash('crc32b', $data, true)) . $data;
        $xzBinary = $this->getXzBinary();

        // we need to get raw lzma1 compressed data with parameters LC=3, LP=0, PB=2, DICT_SIZE=128KiB
        $xzProcess = proc_open("${xzBinary} '--format=raw' '--lzma1=lc=3,lp=0,pb=2,dict=128KiB' '-c' '-'", [
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
     *
     * @return QrCode
     */
    public function getQrImage(): QrCode
    {
        if (!class_exists("Endroid\QrCode\QrCode")) {
            throw new QrPaymentException('Error: library endroid/qr-code is not loaded.');
        }

        return new QrCode($this->getQrString());
    }

    /**
     * @param string|IbanInterface $iban
     *
     * @return static
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

        return new static($iban);
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
     *
     * @return QrPayment
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
     * @param int|null $variableSymbol
     *
     * @return QrPayment
     */
    public function setVariableSymbol(?int $variableSymbol): self
    {
        $this->variableSymbol = $variableSymbol;

        return $this;
    }

    /**
     * @param int|null $specificSymbol
     *
     * @return QrPayment
     */
    public function setSpecificSymbol(?int $specificSymbol): self
    {
        $this->specificSymbol = $specificSymbol;

        return $this;
    }

    /**
     * @param int $constantSymbol
     *
     * @return QrPayment
     */
    public function setConstantSymbol(?int $constantSymbol): self
    {
        $this->constantSymbol = $constantSymbol;

        return $this;
    }

    /**
     * @param string $currency
     *
     * @return QrPayment
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @param string $comment
     *
     * @return QrPayment
     */
    public function setComment(string $comment): self
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @param string $internalId
     *
     * @return QrPayment
     */
    public function setInternalId(string $internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    /**
     * @param DateTimeInterface $dueDate
     *
     * @return QrPayment
     */
    public function setDueDate(?DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    /**
     * @param float $amount
     *
     * @return QrPayment
     */
    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @param string $country
     *
     * @return QrPayment
     */
    public function setCountry(string $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @param string $payeeName
     *
     * @return QrPayment
     */
    public function setPayeeName(string $payeeName): QrPayment
    {
        $this->payeeName = $payeeName;

        return $this;
    }

    /**
     * @return XzBinaryLocatorInterface
     */
    public function getXzBinaryLocator(): XzBinaryLocatorInterface
    {
        return $this->xzBinaryLocator;
    }

    /**
     * @param XzBinaryLocatorInterface $xzBinaryLocator
     *
     * @return QrPayment
     */
    public function setXzBinaryLocator(XzBinaryLocatorInterface $xzBinaryLocator): QrPayment
    {
        $this->xzBinaryLocator = $xzBinaryLocator;

        return $this;
    }

    /**
     * @param string $binaryPath
     *
     * @return $this
     */
    public function setXzBinary(?string $binaryPath): self
    {
        $this->xzBinaryLocator = $this->getXzBinaryLocatorByOs($binaryPath);

        return $this;
    }

    /**
     * @throws QrPaymentException
     *
     * @return string
     */
    public function getXzBinary(): string
    {
        return $this->xzBinaryLocator->getXzBinary();
    }

    /**
     * @return int
     */
    public function getVariableSymbol(): ?int
    {
        return $this->variableSymbol;
    }

    /**
     * @return int
     */
    public function getSpecificSymbol(): ?int
    {
        return $this->specificSymbol;
    }

    /**
     * @return int
     */
    public function getConstantSymbol(): ?int
    {
        return $this->constantSymbol;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * @return string
     */
    public function getComment(): string
    {
        return $this->comment;
    }

    /**
     * @return string
     */
    public function getInternalId(): string
    {
        return $this->internalId;
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getCountry(): string
    {
        return $this->country;
    }

    /**
     * @return string
     */
    public function getPayeeName(): string
    {
        return $this->payeeName;
    }

    /**
     * Checks whether the due date is set.
     * Throws exception if the date format cannot be parsed by strtotime() func
     *
     * @return DateTimeInterface
     */
    public function getDueDate(): DateTimeInterface
    {
        if ($this->dueDate === null) {
            return new DateTime();
        }

        return $this->dueDate;
    }

    /**
     * @param string|null $binaryPath
     *
     * @return XzBinaryLocatorInterface
     */
    private function getXzBinaryLocatorByOs(?string $binaryPath = null)
    {
        if (strpos(php_uname('s'), 'Windows') !== false) {
            return new WindowsXzBinaryLocator($binaryPath);
        } else {
            return new LinuxXzBinaryLocator($binaryPath);
        }
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
