<?php

namespace rikudou\SkQrPayment;

use DateTime;
use Endroid\QrCode\QrCode;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Structs\IbanBicPair;
use RuntimeException;

/**
 * Class QrPayment
 *
 * @property int $variableSymbol
 * @property int $specificSymbol
 * @property int $constantSymbol
 * @property string $currency
 * @property string $comment
 * @property int $repeat
 * @property string $internalId
 * @property string|DateTime $dueDate
 * @property float $amount
 * @property string $country
 * @property string $swift
 *
 * @final
 */
class QrPayment
{
    /** @var  string $account */
    protected $account;

    /** @var  string $bank */
    protected $bank;

    /** @var string|null $iban */
    protected $iban = null;

    /** @var string|null $xzPath */
    protected $xzPath = null;

    /** @var IbanBicPair[] $ibans */
    private $ibans = [];

    /** @var int $variableSymbol */
    private $variableSymbol;

    /** @var int $specificSymbol */
    private $specificSymbol;

    /** @var int $constantSymbol */
    private $constantSymbol;

    /** @var string $currency */
    private $currency = 'EUR';

    /** @var string $comment */
    private $comment = '';

    /** @var int $repeat */
    private $repeat = 7;

    /** @var string $internalId */
    private $internalId = '';

    /** @var string|DateTime $dueDate */
    private $dueDate;

    /** @var float $amount */
    private $amount;

    /** @var string $country */
    private $country = 'SK';

    /** @var string $swift */
    private $swift;

    /**
     * QrPayment constructor.
     * Sets account and bank. Allows to specify options in array in format:
     * property_name => value
     *
     * @param int|string $account
     * @param int|string $bank
     * @param array      $options
     */
    public function __construct($account, $bank, array $options = null)
    {
        $this->account = strval($account);
        $this->bank = strval($bank);

        if ($account && $bank) {
            trigger_error(
                'Using the constructor to generate iban from bank account and code is deprecated, use static methods fromIban or fromIbans',
                E_USER_DEPRECATED
            );
        }

        if ($options) {
            $this->setOptions($options);
        }

        if (get_class($this) !== __CLASS__) {
            trigger_error(
                sprintf("Extending the class '%s' is deprecated as the class will be marked final in future versions", __CLASS__),
                E_USER_DEPRECATED
            );
        }
    }

    public function __get($name)
    {
        static $deprecationTriggered = false;
        $protectedProperties = [
            'account',
            'bank',
            'iban',
            'xzPath',
            'ibans',
        ];
        if (property_exists($this, $name) && !in_array($name, $protectedProperties)) {
            if (!$deprecationTriggered) {
                trigger_error('Direct access to properties is deprecated and will be removed in future versions', E_USER_DEPRECATED);
                $deprecationTriggered = true;
            }

            return $this->{$name};
        }

        throw new RuntimeException(sprintf("Trying to access non-existent property '%s' of class '%s'", $name, __CLASS__));
    }

    public function __set($name, $value)
    {
        static $deprecationTriggered = false;
        $protectedProperties = [
            'account',
            'bank',
            'iban',
            'xzPath',
            'ibans',
        ];
        if (property_exists($this, $name) && !in_array($name, $protectedProperties)) {
            if (!$deprecationTriggered) {
                trigger_error('Direct access to properties is deprecated and will be removed in future versions', E_USER_DEPRECATED);
                $deprecationTriggered = true;
            }

            return $this->{$name} = $value;
        }

        throw new RuntimeException(sprintf("Trying to access non-existent property '%s' of class '%s'", $name, __CLASS__));
    }

    /**
     * Specifies options in array in format:
     * property_name => value
     *
     * @param array $options
     *
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        return $this;
    }

    /**
     * Converts account and bank numbers to IBAN
     *
     * @return string
     */
    public function getIBAN()
    {
        if (!is_null($this->iban)) {
            return $this->iban;
        }
        if (!$this->account || !$this->bank) {
            throw new QrPaymentException('Cannot generate IBAN if there is no bank account and bank code');
        }
        $this->country = strtoupper($this->country);

        $part1 = ord($this->country[0]) - ord('A') + 10;
        $part2 = ord($this->country[1]) - ord('A') + 10;

        $accountPrefix = 0;
        $accountNumber = $this->account;
        if (strpos($accountNumber, '-') !== false) {
            $accountParts = explode('-', $accountNumber);
            $accountPrefix = $accountParts[0];
            $accountNumber = $accountParts[1];
        }

        $numeric = sprintf('%04d%06d%010d%d%d00', $this->bank, $accountPrefix, $accountNumber, $part1, $part2);

        $mod = '';
        foreach (str_split($numeric) as $n) {
            $mod = ($mod . $n) % 97;
        }
        $mod = intval($mod);

        $this->iban = sprintf('%.2s%02d%04d%06d%010d', $this->country, 98 - $mod, $this->bank, $accountPrefix, $accountNumber);

        return $this->iban;
    }

    /**
     * Returns QR Payment string
     * Throws exception if the date is not in format understandable by strtotime() function or you're missing SWIFT
     *
     * @throws QrPaymentException
     *
     * @return string
     */
    public function getQrString()
    {
        try {
            $this->getIBAN();
            $legacy = true;
        } catch (QrPaymentException $e) {
            $legacy = false;
        }
        if ($legacy && !$this->swift) {
            $swift = (new IBANtoBIC($this->getIBAN()))->getBIC();
            if (is_null($swift)) {
                throw new QrPaymentException("The 'swift' option is required, please use 'setSwift(string)'", QrPaymentException::ERR_MISSING_REQUIRED_OPTION);
            }
            $this->swift = $swift;
        }

        if ($legacy) {
            $this->addIban(new IbanBicPair(new IBAN($this->getIBAN()), $this->swift));
        }

        if (!count($this->ibans)) {
            throw new QrPaymentException('Cannot generate QR payment with no IBANs');
        }

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

        foreach ($this->ibans as $iban) {
            $dataArray[2][] = $iban->getIban()->asString();
            $dataArray[2][] = $iban->getBic();
        }

        $dataArray[2][] = 0; // standing order
        $dataArray[2][] = 0; // direct debit
        // can also contain other elements in this order: the payee's name, the payee's address (line 1), the payee's address (line 2)
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
     * @param bool $setPngHeader
     *
     * @throws QrPaymentException
     *
     * @return QrCode
     */
    public function getQrImage($setPngHeader = false)
    {
        if (!class_exists("Endroid\QrCode\QrCode")) {
            throw new QrPaymentException('Error: library endroid/qr-code is not loaded.', QrPaymentException::ERR_MISSING_LIBRARY);
        }

        if ($setPngHeader) {
            header('Content-type: image/png');
        }

        return new QrCode($this->getQrString());
    }

    /**
     * @param string|IbanBicPair $iban
     *
     * @return static
     */
    public static function fromIBAN($iban)
    {
        if ($iban instanceof IbanBicPair) {
            return self::fromIBANs([$iban]);
        }
        trigger_error(
            'Constructing IBAN from string is deprecated',
            E_USER_DEPRECATED
        );
        $instance = new static(0, 0);
        $instance->iban = $iban;

        return $instance;
    }

    /**
     * @param IbanBicPair[] $ibans
     *
     * @throws QrPaymentException
     *
     * @return QrPayment
     */
    public static function fromIBANs(array $ibans): self
    {
        $instance = new static(0, 0);
        foreach ($ibans as $iban) {
            if (!$iban instanceof IbanBicPair) {
                throw new QrPaymentException('All items must be instance of ' . IbanBicPair::class);
            }
            $instance->addIban($iban);
        }

        return $instance;
    }

    /**
     * @param int $variableSymbol
     *
     * @return QrPayment
     */
    public function setVariableSymbol($variableSymbol)
    {
        $this->variableSymbol = $variableSymbol;

        return $this;
    }

    /**
     * @param int $specificSymbol
     *
     * @return QrPayment
     */
    public function setSpecificSymbol($specificSymbol)
    {
        $this->specificSymbol = $specificSymbol;

        return $this;
    }

    /**
     * @param int $constantSymbol
     *
     * @return QrPayment
     */
    public function setConstantSymbol($constantSymbol)
    {
        $this->constantSymbol = $constantSymbol;

        return $this;
    }

    /**
     * @param string $currency
     *
     * @return QrPayment
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;

        return $this;
    }

    /**
     * @param string $comment
     *
     * @return QrPayment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * @param DateTime|string $dueDate
     *
     * @return QrPayment
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    /**
     * @param float $amount
     *
     * @return QrPayment
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;

        return $this;
    }

    /**
     * @param string $country
     *
     * @return QrPayment
     */
    public function setCountry($country)
    {
        $this->country = $country;

        return $this;
    }

    /**
     * @param string $swift
     *
     * @return QrPayment
     */
    public function setSwift($swift)
    {
        $this->swift = $swift;

        return $this;
    }

    /**
     * @param string $binaryPath
     *
     * @return $this
     */
    public function setXzBinary($binaryPath)
    {
        $this->xzPath = $binaryPath;

        return $this;
    }

    /**
     * @param string $internalId
     *
     * @return QrPayment
     */
    public function setInternalId($internalId)
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function addIban(IbanBicPair $iban): self
    {
        if (!isset($this->ibans[$iban->getIban()->asString()])) {
            $this->ibans[$iban->getIban()->asString()] = $iban;
        }

        return $this;
    }

    public function removeIban(IbanBicPair $iban): self
    {
        if (isset($this->ibans[$iban->getIban()->asString()])) {
            unset($this->ibans[$iban->getIban()->asString()]);
        }

        return $this;
    }

    /**
     * @return IbanBicPair[]
     */
    public function getIbans(): array
    {
        return $this->ibans;
    }

    /**
     * @param IbanBicPair[] $ibans
     *
     * @return QrPayment
     */
    public function setIbans(array $ibans):  self
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
     * @throws QrPaymentException
     *
     * @return string
     *
     *
     */
    public function getXzBinary()
    {
        if (is_null($this->xzPath)) {
            exec('which xz', $output, $return);
            if ($return !== 0) {
                throw new QrPaymentException("'xz' binary not found in PATH, specify it using setXzBinary()", QrPaymentException::ERR_MISSING_XZ);
            }
            if (!isset($output[0])) {
                throw new QrPaymentException("'xz' binary not found in PATH, specify it using setXzBinary()", QrPaymentException::ERR_MISSING_XZ);
            }
            $this->xzPath = $output[0];
        }
        if (!file_exists($this->xzPath)) {
            throw new QrPaymentException("The path '{$this->xzPath}' to 'xz' binary is invalid", QrPaymentException::ERR_MISSING_XZ);
        }

        return $this->xzPath;
    }

    /**
     * Checks whether the due date is set.
     * Throws exception if the date format cannot be parsed by strtotime() func
     *
     * @throws QrPaymentException
     *
     * @return DateTime
     */
    protected function getDueDate()
    {
        if (!$this->dueDate) {
            return new DateTime();
        }

        if (!$this->dueDate instanceof DateTime && !@strtotime($this->dueDate)) {
            throw new QrPaymentException("Error: Due date value ({$this->dueDate}) cannot be transformed, you must ensure that the due date value is acceptable by strtotime()", QrPaymentException::ERR_DATE);
        }

        return $this->dueDate instanceof DateTime ? $this->dueDate : new DateTime($this->dueDate);
    }
}
