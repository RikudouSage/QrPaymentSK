# QR code payment (SK) - Pay by Square standard

[![Tests](https://github.com/RikudouSage/QrPaymentSK/actions/workflows/test.yaml/badge.svg)](https://github.com/RikudouSage/QrPaymentSK/actions/workflows/test.yaml)
[![Coverage Status](https://coveralls.io/repos/github/RikudouSage/QrPaymentSK/badge.svg?branch=master)](https://coveralls.io/github/RikudouSage/QrPaymentSK?branch=master)
[![Download](https://img.shields.io/packagist/dt/rikudou/skqrpayment.svg)](https://packagist.org/packages/rikudou/skqrpayment)

This library generates a string that can be embedded into QR code and is recognized by all Slovak banks.

The library requires the `xz` binary to present on the underlying system.

> See also QR code payment generator for [Czech](https://github.com/RikudouSage/QrPaymentCZ) or
> [European](https://github.com/RikudouSage/QrPaymentEU) accounts.

## Installation

`composer require rikudou/skqrpayment`

## Usage

Create an instance with or without IBANs. All IBANs must be instance of `\Rikudou\Iban\Iban\IbanInterface`.

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Iban\IbanBicPair;

// without IBANs
$payment = new QrPayment();

// one IBAN
$payment = new QrPayment(new IBAN('SK1234567890123456'));

// multiple IBANs, there can be as many as you want
$payment = new QrPayment(
    new IBAN('SK1234567890123456'),
    new IBAN('SK1234567890123456'),
    new IbanBicPair('SK1234567890123456', 'BANKBIC')
);
```

There are three implementations of `IbanInterface` present:

- `\Rikudou\Iban\Iban\IBAN` - generic IBAN class which accepts the IBAN as a string
- `\rikudou\SkQrPayment\Iban\IbanBicPair` - allows you to supply your own BIC (Swift) code if the bank is not present in
the [maps](src/IbanToBic/Dictionary)
- `\Rikudou\Iban\Iban\CzechIbanAdapter` - IBAN that can be constructed from the local Czech format (account number and
bank code)

You can also construct new instance from string:

```php
<?php

use rikudou\SkQrPayment\QrPayment;

$payment = QrPayment::fromIBAN('SK1234567890123456');
```

### Changing IBANs after construction

If you want to add/remove IBANs after construction, use `addIban()`, `removeIban()` and `setIbans()` methods.

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;
use rikudou\SkQrPayment\Iban\IbanBicPair;

$payment = new QrPayment();

$iban1 = new IBAN('SK1234567890123456');
$iban2 = new IBAN('SK6543210987654321');

$payment
    ->addIban($iban1)
    ->addIban($iban2);
// object now contains both IBANs

$payment
    ->removeIban($iban2);
// only the first IBAN is now present in the object

// You don't have to store the object, the ibans are considered the same if the string representation is the same:
$payment
    ->removeIban(new IbanBicPair('SK1234567890123456'));

// the object now doesn't contain any IBAN

$payment->setIbans([
    $iban1,
    $iban2
]);
```

### Setting options

You can set all options using the method `setOptions()` or using the respective setters.

> You can use `\rikudou\SkQrPayment\Payment\QrPaymentOptions` constants for option names

Setting options via `setOptions()`:

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Payment\QrPaymentOptions;
use Rikudou\Iban\Iban\IBAN;

$payment = new QrPayment();

$payment->setOptions([
    QrPaymentOptions::AMOUNT => 100,
    QrPaymentOptions::COMMENT => 'payment',
    QrPaymentOptions::CONSTANT_SYMBOL => 123,
    QrPaymentOptions::COUNTRY => 'SK',
    QrPaymentOptions::CURRENCY => 'EUR',
    QrPaymentOptions::DUE_DATE => new DateTime('+1 week'),
    QrPaymentOptions::INTERNAL_ID => '456',
    QrPaymentOptions::PAYEE_NAME => 'John Doe',
    QrPaymentOptions::SPECIFIC_SYMBOL => 789,
    QrPaymentOptions::VARIABLE_SYMBOL => 012,
    QrPaymentOptions::XZ_PATH => '/path/to/xz',
    QrPaymentOptions::IBANS => [
        new IBAN('SK1234567890123456')
    ]
]);
```

Setting using the fluent setters:

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;

$payment = new QrPayment();
$payment
    ->setAmount(100)
    ->setComment('payment')
    ->setConstantSymbol(123)
    ->setCountry('SK')
    ->setCurrency('EUR')
    ->setDueDate(new DateTime('+1 week'))
    ->setInternalId('456')
    ->setPayeeName('John Doe')
    ->setSpecificSymbol(789)
    ->setVariableSymbol(012)
    ->setXzBinary('/path/to/xz')
    ->setIbans([
        new IBAN('SK01234567890123456')
    ]);
```

### Defaults

Default values for some of the options are provided:

- `currency` - EUR
- `country` - SK
- `dueDate` - current date and time

Additionally, these properties are not required:

- `variableSymbol`
- `specificSymbol`
- `constantSymbol`
- `comment`
- `internalId`
- `payeeName`

### The xz binary

Since the Pay by Square standard uses lzma1 which has no php binding, the xz binary needs to be called.

If you have it in the standard PATH, this library should find it on its own, if not you have to set the path manually
using `setXzBinary()`.

If you want to implement custom logic for getting the binary path, you can create a custom class implementing
`\rikudou\SkQrPayment\Xz\XzBinaryLocatorInterface` and set the object via `setXzBinaryLocator()` in the payment object.

### Getting the result

Once you configured all your options, simply call `getQrString()` and process it using your favorite qr code library.

Alternatively, if you use `endroid/qr-code`, you can call `getQrImage()` to get an instance of `\Endroid\QrCode\QrCode`.

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use Rikudou\Iban\Iban\IBAN;

$payment = new QrPayment(new IBAN('SK6807200002891987426353'));

$payment
    ->setAmount(500)
    ->setVariableSymbol(123456)
    ->setDueDate(new DateTime('+1 week'))
;

$qrString = $payment->getQrString();

// $qrString now holds the string to embed inside the QR code, in this example:
// 0004U0001M8GLP3E8KPT058IQ99QISMB02IH36MOD4BCKQQGVDE4641AOA2NURPCOPSALFG0LPG1C6N0E2JMC7RG2F4L2E57OCSHOUROGHOC8VTTPHHRFHU6VFTM8N80

$qrCode = $payment->getQrImage();

// send to browser
header('Content-Type: ' . $qrCode->getContentType());
echo $qrCode->writeString();

```

### Exceptions

All exceptions extend the base `\rikudou\SkQrPayment\Exception\QrPaymentException`.

- `\rikudou\SkQrPayment\Exception\DictionaryNotFoundException` - when you don't provide a BIC and there is no map for
given country code (currently only `CZ` and `SK`)
    - extends `\rikudou\SkQrPayment\Exception\BicNotFoundException`
- `\rikudou\SkQrPayment\Exception\BicNotFoundException` - when you don't provide a BIC and the bank is not found in
the provided maps
- `\rikudou\SkQrPayment\Exception\InvalidTypeException` - when you try to create an instance using `fromIBAN()` and
the IBAN is not an instance of `QrPaymentInterface` or a string
- `\rikudou\SkQrPayment\Exception\QrPaymentException` - the base exception
    - when you call `getQrImage()` and don't have the `endroid/qr-code` library installed
    - when you call `getQrString()` without providing any IBANs
    - when you call `getQrString()` or `getXzBinary()` and the `xz` binary is not present on filesystem
    - when you create instance of `IbanBicPair` with an argument that is not instance of `IbanInterface` nor string 
    - when you create instance of `IbanBicPair` or call `getQrString()` and any of the IBANs is not valid
