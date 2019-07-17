<?php

namespace rikudou\SkQrPayment;

use RuntimeException;

/**
 * @final
 */
class QrPaymentException extends RuntimeException
{
    public const ERR_MISSING_XZ = 2 << 0;
    public const ERR_DATE = 2 << 1;
    public const ERR_MISSING_LIBRARY = 2 << 2;
    public const ERR_MISSING_REQUIRED_OPTION = 2 << 3;
    public const ERR_FAILED_TO_CALCULATE_HASH = 2 << 4;
}
