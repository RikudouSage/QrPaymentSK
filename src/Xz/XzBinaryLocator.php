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
            exec('which xz', $output, $return);
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
