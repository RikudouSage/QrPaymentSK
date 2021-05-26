<?php

namespace rikudou\SkQrPayment\Xz;

final class XzBinaryLocator implements XzBinaryLocatorInterface
{
    public function getXzBinary(): string
    {
        $error = sprintf('The class "%s" is deprecated, please use "%s" or "%s"', XzBinaryLocator::class, LinuxXzBinaryLocator::class, WindowsXzBinaryLocator::class);
        trigger_error($error, E_USER_DEPRECATED);
        class_alias(LinuxXzBinaryLocator::class, XzBinaryLocator::class);

        return $error;
    }
}
