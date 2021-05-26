<?php

namespace rikudou\SkQrPayment\Payment;

final class QrPaymentOptions
{
    public const XZ_PATH = 'xzBinary';
    public const IBANS = 'ibans';
    public const VARIABLE_SYMBOL = 'variableSymbol';
    public const SPECIFIC_SYMBOL = 'specificSymbol';
    public const CONSTANT_SYMBOL = 'constantSymbol';
    public const CURRENCY = 'currency';
    public const COMMENT = 'comment';
    public const INTERNAL_ID = 'internalId';
    public const DUE_DATE = 'dueDate';
    public const AMOUNT = 'amount';
    public const COUNTRY = 'country';
    public const PAYEE_NAME = 'payeeName';
    public const PAYEE_ADDRESS_LINE1 = 'payeeAddressLine1';
    public const PAYEE_ADDRESS_LINE2 = 'payeeAddressLine2';
}
