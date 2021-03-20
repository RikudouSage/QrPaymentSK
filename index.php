<?php
require "vendor/autoload.php";

use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;

$payment = new QrPayment(new IBAN('SK6807200002891987426353'));

$payment
    ->setAmount(500)
    ->setVariableSymbol(123456)
    ->setDueDate(new DateTime('+1 week'))
;

$qrCode = $payment->getQrImage();

// send to browser
header('Content-Type: ' . $qrCode->getContentType());
echo $qrCode->writeString();
