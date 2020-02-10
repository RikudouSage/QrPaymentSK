<?php

namespace rikudou\SkQrPayment;

final class QrPaymentFlag
{
    /**
     * If you don't want to check whether the xz binary exists
     */
    public const NO_BINARY_CHECK = 2 << 0;
}
