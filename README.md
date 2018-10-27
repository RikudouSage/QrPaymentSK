# QR code payment (SK)

A simple library to generate QR payment code for Slovakia.
All methods are documented in source code.
This library needs the `xz` binary to be present. If the binary is not in your PATH,
you must set the path to `xz` manually.

> [See also QR code payment generator for Czech accounts](https://github.com/RikudouSage/QrPaymentCZ).

> Using Symfony? See the [QR Payment Bundle](https://github.com/RikudouSage/QrPaymentBundle).

## Installation

Via composer: `composer require rikudou/skqrpayment`

Manually: clone the repository and include the `QrPaymentException.php`,
`QrPaymentOptions.php` and `QrPayment.php` in your project.

## Usage

You can create the Qr payment from IBAN or BBAN.


From IBAN:

```php
<?php

use rikudou\SkQrPayment\QrPayment;

$payment = QrPayment::fromIBAN("SK6807200002891987426353");
```
From account number and bank code (BBAN):

```php
<?php

use rikudou\SkQrPayment\QrPayment;

$payment = new QrPayment("0123123123", "0900");

```

### Setting payment details

There are two approaches to setting payment details. You can set them in associative array or using the methods
provided in the class.

**Using associative array**

```php
<?php

use rikudou\SkQrPayment\QrPayment;
use rikudou\SkQrPayment\QrPaymentOptions;

$payment = QrPayment::fromIBAN("SK6807200002891987426353")->setOptions([
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

$payment = QrPayment::fromIBAN("SK6807200002891987426353")
    ->setVariableSymbol(123456)
    ->setAmount(100)
    ->setCurrency("EUR")
    ->setDueDate(date("Y-m-d", strtotime("+14 days")));
```

## Exceptions

The only exception thrown by this library is `rikudou\SkQrPayment\QrPaymentException`.

**Methods that can throw exception:**

- `getQrString()` - if you're missing SWIFT
(the library will try to find SWIFT automatically from map)
or if the date is not a valid date or if the hash calculation fails
- `getQrImage()` - if any property contains asterisk(`*`) or if the date is not a valid date
or if the `endroid\qrcode` is not loaded
- `getXzBinary()` - if the `xz` binary is not available

**Error codes**

The `QrPaymentException` contains constants to help you debugging the reason for the exception throw.

- `QrPaymentException::ERR_MISSING_XZ` - this code is thrown when `xz` is not in your binary path
- `QrPaymentException::ERR_DATE` - this code is thrown if the date is not a valid date
- `QrPaymentException::ERR_MISSING_LIBRARY` - this code is thrown if you try to use `getQrImage()` method but don't have
the `endroid\qrcode` library installed
- `QrPaymentException::ERR_MISSING_REQUIRED_OPTION` - when you're missing any option that is required when you try to generate qr string
- `QrPaymentException::ERR_FAILED_TO_CALCULATE_HASH` - when the hash calculation fails due tu unknown error


## List of public methods

### Constructor

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

$payment = QrPayment::fromIBAN("SK6807200002891987426353")->setOptions([
  QrPaymentOptions::AMOUNT => 100
]);
```

### getIBAN()

Returns the IBAN, either from supplied IBAN or generated from account number and 
bank code.


**Returns**

`string`

**Example**

```php
<?php

use rikudou\SkQrPayment\QrPayment;

$payment = QrPayment::fromIBAN("SK6807200002891987426353");
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

$payment = QrPayment::fromIBAN("SK6807200002891987426353")->setOptions([
  QrPaymentOptions::AMOUNT => 100,
  QrPaymentOptions::VARIABLE_SYMBOL => 1502,
  QrPaymentOptions::DUE_DATE => date("Y-m-d", strtotime("+14 days"))    
]);

$qrString = $payment->getQrString();
```

### static fromIBAN()

Returns new instance of the payment object created from IBAN.

**Params**

- `string $iban` - The IBAN of the account

**Returns**

Returns itself, you can use this method for chaining.

**Example**

```php
<?php
use rikudou\SkQrPayment\QrPayment;

$payment = QrPayment::fromIBAN("SK6807200002891987426353");
// do all the other stuff
```

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