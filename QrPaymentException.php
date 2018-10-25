<?php

namespace rikudou\SkQrPayment;

class QrPaymentException extends \Exception
{
    const ERR_MISSING_XZ = 1;
    const ERR_DATE = 2;
    const ERR_MISSING_LIBRARY = 3;
    const ERR_MISSING_REQUIRED_OPTION = 5;
    const ERR_FAILED_TO_CALCULATE_HASH = 6;
}