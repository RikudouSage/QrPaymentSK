<?php

namespace rikudou\SkQrPayment\Exception;

use Throwable;

final class InvalidTypeException extends QrPaymentException
{
    /**
     * InvalidTypeException constructor.
     *
     * @param string|array<string> $expected
     * @param mixed                $actual
     * @param int                  $code
     * @param Throwable|null       $previous
     */
    public function __construct($expected, $actual, $code = 0, Throwable $previous = null)
    {
        if (is_array($expected)) {
            $expected = implode("' or '", $expected);
        }
        if (!is_string($expected)) {
            $expected = strval($expected);
        }

        $message = sprintf(
            "Expected '%s', got '%s'",
            $expected,
            $this->getType($actual)
        );

        parent::__construct($message, $code, $previous);
    }

    /**
     * @param mixed $variable
     *
     * @return string
     */
    private function getType($variable): string
    {
        if (is_object($variable)) {
            return get_class($variable);
        } else {
            return gettype($variable);
        }
    }
}
