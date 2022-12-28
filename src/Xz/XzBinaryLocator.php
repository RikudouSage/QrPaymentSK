<?php

namespace rikudou\SkQrPayment\Xz;

use rikudou\SkQrPayment\Exception\QrPaymentException;

final class XzBinaryLocator implements XzBinaryLocatorInterface
{
    /**
     * @var string|null
     */
    private $path;

    public function __construct(?string $path)
    {
        $this->path = $path;
    }

    public function getXzBinary(): string
    {
        if ($this->path === null) {
            if (stristr(PHP_OS, 'LINUX')) { // on Linux which command is used to find xz
                exec( 'which xz', $output, $return );
            } elseif (stristr(PHP_OS, 'WIN')) { // on Windos where command is used to find xz
                exec( 'where xz', $output, $return );
            } else { 
                // if you are using other host than Linux or Windows you should add elseif branch or change entire if/else to switch/case via pull request
                // or specify it using setXzBinary()
                throw new QrPaymentException("not supported OS for auto find xz binary, specify it using setXzBinary()");
            }
            if ($return !== 0) {
                // @codeCoverageIgnoreStart
                throw new QrPaymentException("'xz' binary not found in PATH, specify it using setXzBinary()");
                // @codeCoverageIgnoreEnd
            }
            if (!isset($output[0])) {
                // @codeCoverageIgnoreStart
                throw new QrPaymentException("'xz' binary not found in PATH, specify it using setXzBinary()");
                // @codeCoverageIgnoreEnd
            }
            $this->path = $output[0];
        }
        if (!file_exists($this->path)) {
            throw new QrPaymentException("The path '{$this->path}' to 'xz' binary is invalid");
        }

        return $this->path;
    }
}
