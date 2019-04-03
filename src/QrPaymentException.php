<?php

namespace rikudou\SkQrPayment;

/**
 * @final
 */
class QrPaymentException extends \Exception
{
    public const ERR_MISSING_XZ = 1;
    public const ERR_DATE = 2;
    public const ERR_MISSING_LIBRARY = 3;
    public const ERR_MISSING_REQUIRED_OPTION = 5;
    public const ERR_FAILED_TO_CALCULATE_HASH = 6;
}
