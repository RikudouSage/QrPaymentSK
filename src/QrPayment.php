<?php

namespace rikudou\SkQrPayment;

use DateTime;
use Endroid\QrCode\QrCode;

/**
 * Class QrPayment
 */
class QrPayment
{
    /** @var int $variableSymbol */
    public $variableSymbol;

    /** @var int $specificSymbol */
    public $specificSymbol;

    /** @var int $constantSymbol */
    public $constantSymbol;

    /** @var string $currency */
    public $currency = 'EUR';

    /** @var string $comment */
    public $comment = '';

    /** @var int $repeat */
    public $repeat = 7;

    /** @var string $internalId */
    public $internalId = '';

    /** @var string|DateTime $dueDate */
    public $dueDate;

    /** @var float $amount */
    public $amount;

    /** @var string $country */
    public $country = 'SK';

    /** @var string $swift */
    public $swift;

    /** @var  string $account */
    protected $account;

    /** @var  string $bank */
    protected $bank;

    /** @var string|null $iban */
    protected $iban = null;

    /** @var string|null $xzPath */
    protected $xzPath = null;

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

        if ($options) {
            $this->setOptions($options);
        }
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
     *
     */
    public function getQrString()
    {
        if (!$this->swift) {
            $swift = (new IBANtoBIC($this->getIBAN()))->getBIC();
            if (is_null($swift)) {
                throw new QrPaymentException("The 'swift' option is required, please use 'setSwift(string)'", QrPaymentException::ERR_MISSING_REQUIRED_OPTION);
            }
            $this->swift = $swift;
        }

        $data = implode("\t", [
            0 => $this->internalId, // payment identifier (can be anything)
            1 => '1', // count of payments
            2 => implode("\t", [
                true, // regular payment
                round($this->amount, 2),
                $this->currency,
                $this->getDueDate()->format('Ymd'),
                $this->variableSymbol,
                $this->constantSymbol,
                $this->specificSymbol,
                '', // variable symbol, constant symbol and specific symbol in SEPA format (empty because the 3 previous are already defined)
                $this->comment,
                '1', // one target account
                $this->getIBAN(),
                $this->swift,
                '0', // standing order
                '0', // direct debit
                // can also contain other elements in this order: the payee's name, the payee's address (line 1), the payee's address (line 2)
            ]),
        ]);

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

        if (!$hashedData) {
            throw new QrPaymentException('Failed to calculate hash due to unknown error.', QrPaymentException::ERR_FAILED_TO_CALCULATE_HASH);
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
     *@throws QrPaymentException
     *
     * @return \Endroid\QrCode\QrCode
     *
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
     * @param string $iban
     *
     * @throws QrPaymentException
     *
     * @return static
     *
     */
    public static function fromIBAN($iban)
    {
        $instance = new static(0, 0);
        $instance->iban = $iban;

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

    /**
     * @throws QrPaymentException
     *
     * @return string
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
     *
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
