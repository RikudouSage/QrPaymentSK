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
			switch ( strtoupper( PHP_OS ) ) {
				case 'LINUX':
					exec( 'which xz', $output, $return );
					break;
				case 'WIN':
					exec( 'where xz', $output, $return );
					break;
				default:
					throw new QrPaymentException( "not supported OS for auto find xz binary, specify it using setXzBinary()" );
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
