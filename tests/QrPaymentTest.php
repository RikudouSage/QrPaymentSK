<?php

namespace rikudou\SkQrPayment\Tests;

use rikudou\SkQrPayment\QrPayment;
use PHPUnit\Framework\TestCase;
use rikudou\SkQrPayment\QrPaymentException;
use rikudou\SkQrPayment\QrPaymentFlag;

class QrPaymentTest extends TestCase
{
    public function testNoBinaryCheckFlag()
    {
        $instance = new QrPayment(null, null, [], QrPaymentFlag::NO_BINARY_CHECK);
        $instance->setXzBinary('/nonexistent/path/to/xz');

        // nothing should happen since the flag is present
        $instance->getXzBinary();

        $instance = new QrPayment(null, null);
        $instance->setXzBinary('/nonexistent/path/to/xz');

        // without the flag an exception should be thrown
        $this->expectException(QrPaymentException::class);
        $instance->getXzBinary();
    }
}
