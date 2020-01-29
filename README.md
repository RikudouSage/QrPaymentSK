# QR code payment (SK)

![Build Status](https://travis-ci.com/RikudouSage/QrPaymentSK.svg?branch=master "master build status")
[![Download](https://img.shields.io/packagist/dt/rikudou/skqrpayment.svg)](https://packagist.org/packages/rikudou/skqrpayment)

A simple library to generate QR payment code for Slovakia.
All methods are documented in source code.

This library needs the `xz` binary to be present. If the binary is not in your PATH,
you must set the path to `xz` manually. [Download the xz-utils](https://tukaani.org/xz/).

> See also QR code payment generator for
[Czech](https://github.com/RikudouSage/QrPaymentCZ)
or [European](https://github.com/RikudouSage/QrPaymentEU) accounts.

> Using Symfony? See the [QR Payment Bundle](https://github.com/RikudouSage/QrPaymentBundle).

## Installation

Via composer: `composer require rikudou/skqrpayment`

## Usage

> Extending the QrPayment class was deprecated as of 2.4.0

You can create the Qr payment from IBAN or BBAN.


From IBAN:

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'));
// or multiple accounts
$payment = QrPayment::fromIBANs([
    new IbanBicPair('SK6807200002891987426353'),
    new IbanBicPair('SK3302000000000000012351')
]);
// you can also specify the BIC (SWIFT) code in the IbanBicPair
$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353', 'NBSBSKBX'));
// if you don't specify the BIC it will be guessed automatically
```
From account number and bank code (BBAN):

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Structs\IbanBicPair;
use rikudou\SkQrPayment\Structs\SlovakianIbanAdapter;

$payment = QrPayment::fromIBAN(
    new IbanBicPair(
        new SlovakianIbanAdapter('0123123123', '0900')
    )
);

```

### Setting payment details

There are two approaches to setting payment details. You can set them in associative array or using the methods
provided in the class.

**Using associative array**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'))->setOptions([
  QrPaymentOptions::VARIABLE_SYMBOL => 123456,
  QrPaymentOptions::AMOUNT => 100,
  QrPaymentOptions::CURRENCY => "EUR",
  QrPaymentOptions::DUE_DATE => date("Y-m-d", strtotime("+14 days"))    
]);

```

**Using methods**

```php
<?php
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'))
    ->setVariableSymbol(123456)
    ->setAmount(100)
    ->setCurrency("EUR")
    ->setDueDate(date("Y-m-d", strtotime("+14 days")));
```

## Exceptions

The only exception thrown by this library is `rikudou\SkQrPayment\QrPaymentException`.

**Methods that can throw exception:**

- `IbanBicPair::__construct()` - if the IBAN is of invalid type (not `string` or `IbanInterface`), if the BIC is not
supplied and could not be automatically guessed and if the IBAN is not valid
- `QrPayment::getQrString()` - if there are no IBANs set
- `QrPayment::getQrImage()` - if the `endroid\qrcode` is not loaded
- `QrPayment::fromIBANs()` - if any of the the IBANs is not instance of `IbanBicPair`
- `QrPayment::getXzBinary()` - if the `xz` binary is not available
- `QrPayment::getDueDate()` - if the supplied date could not be parsed into `DateTime` object

## List of public methods

### Constructor

> Deprecated as of 2.5.0

**Params**

- `int|string $account` - the account number
- `int|string $bank` - the bank code
- `array $options` - the array with options (not required).
The helper class `QrPaymentOptions` can be used for options names.

**Example**

```php
<?php
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;

$payment = new QrPayment(1325090010, 3030);

// or with options

$payment = new QrPayment(1325090010, 3030, [
  QrPaymentOptions::AMOUNT => 100
]);
```

### setOptions()

Sets the options, useful if you create object from IBAN.

**Params**

- `array $options` - the same as the constructor param `$options`

**Returns**

Returns itself, you can use this method for chaining.

**Example**

```php
<?php
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'))->setOptions([
  QrPaymentOptions::AMOUNT => 100
]);
```

### getIBAN()

> Deprecated as of 2.5.0

Returns the IBAN, either from supplied IBAN or generated from account number and 
bank code.


**Returns**

`string`

**Example**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'));
$myIBAN = $payment->getIBAN();
```

### getQrString()

Returns the string that should be encoded in QR image.

**Returns**

`string`

**Example**

```php
<?php
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(
    new IbanBicPair('SK6807200002891987426353')
)->setOptions([
  QrPaymentOptions::AMOUNT => 100,
  QrPaymentOptions::VARIABLE_SYMBOL => 1502,
  QrPaymentOptions::DUE_DATE => new DateTime('+14 days')    
]);

$qrString = $payment->getQrString();
```

### static fromIBAN()

Returns new instance of the payment object created from IBAN.

**Params**

> Passing string as the $iban argument is deprecated as of 2.5.0

- `IbanBicPair|string $iban` - The IBAN of the account

**Returns**

Returns new instance.

**Example**

```php
<?php
use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\Structs\IbanBicPair;

$payment = QrPayment::fromIBAN(new IbanBicPair('SK6807200002891987426353'));
// do all the other stuff
```

### static fromIBANs()

Returns new instance of the payment object with multiple IBANs.

**Params**

- `IbanBicPair[] $ibans` - array of `IbanBicPair` objects

**Returns**

Returns new instance.

### addIban()

Adds another IBAN to the payment object. Duplicate IBANs will
be discarded silently (e.g. you can add the same IBAN multiple
times, it will be added only once).

**Params**

- `IbanBicPair $iban` - the IBAN to add

**Returns**

Returns itself.

### removeIban()

Removes the IBAN from payment. If the IBAN is not present,
does nothing (e.g. you don't need to check whether the IBAN
was actually added to payment before attempting to remove).

**Params**

- `IbanBicPair $iban` - the IBAN to remove

**Returns**

Returns itself.

### getIbans()

Returns all IBANs in array.

**Returns**

`IbanBicPair[]` (e.g. array of `IbanBicPair`).

### setIbans()

Sets all the IBANs for payment, replaces any previous IBANs.

**Params**

- `IbanBicPair[] $ibans` - the array with IBANs

**Returns**

Returns itself.

### getQrImage()

Returns a Qr code via third-party library.

**Params**

- `bool $setPngHeader` - if true, this method calls `header()` function to set
content type to image/png, defaults to false

**Returns**

`\Endroid\QrCode\QrCode`

**Example**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;

$payment = QrPayment::fromIBAN("SK6807200002891987426353")->setOptions([
  QrPaymentOptions::AMOUNT => 100
]);

$payment->getQrImage(true) // sets the content-type and renders
    ->writeString();

```

### getXzBinary()

Returns the path to the `xz` binary. If the binary is not set via `setXzBinary()`
it tries to get the `xz` binary path from system.

Throws exception if neither succeeds.

**Returns**

`string`

**Example**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentException;

$payment = QrPayment::fromIBAN("SK6807200002891987426353");

try {
  $payment->getXzBinary();
} catch (QrPaymentException $exception) {
  // the xz binary was not found in PATH
}

```

### setXzBinary()

**Params**

- `string $binaryPath` - the path to the `xz` binary

**Returns**

Returns itself, you can use this method for chaining.

**Example**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentException;

$payment = QrPayment::fromIBAN("SK6807200002891987426353");

echo $payment->getXzBinary(); // prints /usr/bin/xz or something similar
$payment->setXzBinary("/path/to/xz");
echo $payment->getXzBinary(); // prints /path/to/xz

```

### Options

> Direct access to properties (e.g. `$payment->amount = 100`) was deprecated
as of 2.4.0

This is a list of options you can set.

- `int variableSymbol` - the variable symbol, has no default
- `int specificSymbol` - the specific symbol, has no default
- `int constantSymbol` - the constant symbol, has no default
- `string currency` - three letter code for currency, defaults to `EUR`
- `string comment` - the payment comment, has no default
- `string|DateTime dueDate` - the due date for payment, should be an instance of
`DateTime` class or a string that can be parsed by `strtotime()`, has no default
- `float amount` - the amount for the payment, can't have more than 2 decimal places,
has no default
- `country` - two letter code for country, defaults to `SK`

All of these options can be set using the `QrPaymentOptions` helper class as constants
for constructor or `setOptions()` or as methods.

For example, the `amount` can be set in array using the constant
`QrPaymentOptions::AMOUNT` or using the method `setAmount()`.
