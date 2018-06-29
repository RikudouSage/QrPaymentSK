<?php

namespace rikudou\SkQrPayment;

use Endroid\QrCode\QrCode;

/**
 * Class QrPayment
 */
class QrPayment
{

    /** @var  int|string $account */
    protected $account;
    /** @var  int $bank */
    protected $bank;

    /** @var int $variableSymbol */
    public $variableSymbol;
    /** @var int $specificSymbol */
    public $specificSymbol;
    /** @var int $constantSymbol */
    public $constantSymbol;
    /** @var string $currency */
    public $currency = "EUR";
    /** @var string $comment */
    public $comment = "";
    /** @var int $repeat */
    public $repeat = 7;
    /** @var string $internalId */
    public $internalId;
    /** @var string|\DateTime $dueDate */
    public $dueDate;
    /** @var float $amount */
    public $amount;
    /** @var string $country */
    public $country = 'SK';
    /** @var string $swift */
    public $swift;
    /** @var string|null $iban */
    protected $iban = null;

    /**
     * QrPayment constructor.
     * Sets account and bank. Allows to specify options in array in format:
     * property_name => value
     *
     * @param int|string $account
     * @param int|string $bank
     * @param array $options
     * @throws QrPaymentException
     */
    public function __construct($account, $bank, array $options = null)
    {

        if (PHP_OS != "Linux") {
            throw new QrPaymentException("This library currently supports only Linux", QrPaymentException::ERR_UNSUPPORTED_OS);
        }

        exec("which xz", $null, $return);
        if ($return !== 0) {
            throw new QrPaymentException("This library requires the 'xz' binary in your path", QrPaymentException::ERR_MISSING_XZ);
        }

        $this->account = $account;
        $this->bank = $bank;

        if ($options) {
            $this->setOptions($options);
        }
    }

    /**
     * Specifies options in array in format:
     * property_name => value
     *
     * @param array $options
     * @return $this
     */
    public function setOptions(array $options)
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }

        return $this;
    }

    /**
     * Converts account and bank numbers to IBAN
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

        $numeric = sprintf("%04d%016d%d%d00", $this->bank, $this->account, $part1, $part2);

        $mod = "";
        foreach (str_split($numeric) as $n) {
            $mod = ($mod . $n) % 97;
        }

        $this->iban = sprintf("%.2s%02d%04d%016d", $this->country, 98 - $mod, $this->bank, $this->account);
        return $this->iban;
    }

    /**
     * Returns QR Payment string
     * Throws exception if the date is not in format understandable by strtotime() function or you're missing SWIFT
     *
     * @return string
     * @throws \rikudou\SkQrPayment\QrPaymentException
     */
    public function getQrString()
    {
        if (!$this->swift) {
            throw new QrPaymentException("The 'swift' option is required, please use 'setSwift(string)'", QrPaymentException::ERR_MISSING_REQUIRED_OPTION);
        }

        $data = implode("\t", [
            0 => '',
            1 => '1',
            2 => implode("\t", [
                true,
                round($this->amount, 2),
                $this->currency,
                $this->getDueDate()->format("Ymd"),
                $this->variableSymbol,
                $this->constantSymbol,
                $this->specificSymbol,
                '',
                $this->comment,
                '1',
                $this->getIBAN(),
                $this->swift,
                '0',
                '0'
            ])
        ]);

        $hashedData = strrev(hash("crc32b", $data, true)) . $data;
        $xzBinary = trim(`which xz`);

        $xzProcess = proc_open("$xzBinary '--format=raw' '--lzma1=lc=3,lp=0,pb=2,dict=128KiB' '-c' '-'", [
            0 => [
                "pipe",
                "r"
            ],
            1 => [
                "pipe",
                "w"
            ]
        ], $xzProcessPipes);

        fwrite($xzProcessPipes[0], $hashedData);
        fclose($xzProcessPipes[0]);

        $pipeOutput = stream_get_contents($xzProcessPipes[1]);
        fclose($xzProcessPipes[1]);
        proc_close($xzProcess);

        $hashedData = bin2hex("\x00\x00" . pack("v", strlen($hashedData)) . $pipeOutput);

        $base64Data = "";
        for ($i = 0; $i < strlen($hashedData); $i++) {
            $base64Data .= str_pad(base_convert($hashedData[$i], 16, 2), 4, "0", STR_PAD_LEFT);
        }

        $length = strlen($base64Data);

        $controlDigit = $length % 5;
        if ($controlDigit > 0) {
            $count = 5 - $controlDigit;
            $base64Data .= str_repeat("0", $count);
            $length += $count;
        }

        $length = $length / 5;

        $hashedData = str_repeat("_", $length);
        for ($i = 0; $i < $length; $i++) {
            $hashedData[$i] = "0123456789ABCDEFGHIJKLMNOPQRSTUV"[bindec(substr($base64Data, $i * 5, 5))];
        }

        if(!$hashedData) {
            throw new QrPaymentException("Failed to calculate hash due to unknown error.", QrPaymentException::ERR_FAILED_TO_CALCULATE_HASH);
        }

        return $hashedData;
    }

    /**
     * Checks whether the due date is set.
     * Throws exception if the date format cannot be parsed by strtotime() func
     *
     * @return \DateTime|null
     * @throws \rikudou\SkQrPayment\QrPaymentException
     */
    protected function getDueDate()
    {
        if (!$this->dueDate) {
            return new \DateTime();
        }

        if (!$this->dueDate instanceof \DateTime && !@strtotime($this->dueDate)) {
            throw new QrPaymentException("Error: Due date value ($this->dueDate) cannot be transformed, you must ensure that the due date value is acceptable by strtotime()", QrPaymentException::ERR_DATE);
        }

        return $this->dueDate instanceof \DateTime ? $this->dueDate : new \DateTime($this->dueDate);
    }

    /**
     * Return QrCode object with QrString set, for more info see Endroid QrCode
     * documentation
     *
     * @param bool $setPngHeader
     * @return \Endroid\QrCode\QrCode
     * @throws \rikudou\SkQrPayment\QrPaymentException
     */
    public function getQrImage($setPngHeader = false)
    {
        if (!class_exists("Endroid\QrCode\QrCode")) {
          throw new QrPaymentException("Error: library endroid/qr-code is not loaded.", QrPaymentException::ERR_MISSING_LIBRARY);
        }

        if ($setPngHeader) {
            header("Content-type: image/png");
        }

        return new QrCode($this->getQrString());
    }

    /**
     * @param string $iban
     *
     * @return static
     * @throws \rikudou\SkQrPayment\QrPaymentException
     */
    public static function fromIBAN($iban)
    {
        $instance = new static(0, 0);
        $instance->iban = $iban;
        return $instance;
    }

    /**
     * @param int $variableSymbol
     * @return QrPayment
     */
    public function setVariableSymbol($variableSymbol)
    {
        $this->variableSymbol = $variableSymbol;
        return $this;
    }

    /**
     * @param int $specificSymbol
     * @return QrPayment
     */
    public function setSpecificSymbol($specificSymbol)
    {
        $this->specificSymbol = $specificSymbol;
        return $this;
    }

    /**
     * @param int $constantSymbol
     * @return QrPayment
     */
    public function setConstantSymbol($constantSymbol)
    {
        $this->constantSymbol = $constantSymbol;
        return $this;
    }

    /**
     * @param string $currency
     * @return QrPayment
     */
    public function setCurrency($currency)
    {
        $this->currency = $currency;
        return $this;
    }

    /**
     * @param string $comment
     * @return QrPayment
     */
    public function setComment($comment)
    {
        $this->comment = $comment;
        return $this;
    }

    /**
     * @param \DateTime|string $dueDate
     * @return QrPayment
     */
    public function setDueDate($dueDate)
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    /**
     * @param float $amount
     * @return QrPayment
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * @param string $country
     * @return QrPayment
     */
    public function setCountry($country)
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @param string $swift
     * @return QrPayment
     */
    public function setSwift($swift)
    {
        $this->swift = $swift;
        return $this;
    }

}