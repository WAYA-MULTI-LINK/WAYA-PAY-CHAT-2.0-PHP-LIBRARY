# wayapay-php


A PHP API wrapper for [Wayapay](https://wayapay.ng/).

[![Wayapay](img/wayapay.png?raw=true "Wayapay")](https://wayapay.ng/)

## Requirements
- Curl 7.34.0 or more recent (Unless using Guzzle)
- PHP 5.4.0 or more recent

## Install

### Via Composer

``` bash
    $ composer require pils36/wayapay-php
```

### Via download

Download a release version from the [releases page](https://github.com/Pils36/wayapay-php/releases).
Extract, then:
``` php
    require 'path/to/src/autoload.php';
```

## Usage

Do a redirect to the authorization URL received from calling the /transaction endpoint. This URL is valid for one time use, so ensure that you generate a new URL per transaction.

When the payment is successful, we will call your callback URL (as setup in your dashboard or while initializing the transaction) and return the reference sent in the first step as a query parameter.

If you use a test secret key, we will call your test callback url, otherwise, we'll call your live callback url.

### 0. Prerequisites
Confirm that your server can conclude a TLSv1.2 connection to Wayapay's servers. Most up-to-date software have this capability. Contact your service provider for guidance if you have any SSL errors.
*Don't disable SSL peer verification!*

### 1. Prepare your parameters
`email`, `amount`, `description`, `wayaPublicKey` and `merchantId` are the most common compulsory parameters.

### 2. Initialize a transaction
Initialize a transaction by calling our API.

```php

    require_once('./vendor/autoload.php');

    $wayapay = new \Pils36\Wayapay;
    
    try
    {

      $tranx = $wayapay->transaction->initialize([
        'amount'=>"128.00",     // string   
        'description'=>"Order for something", // string
        'currency'=>566, // int
        'fee'=>1, // int
        'customer'=> ['name' => "Like Vincent", 'email' => "wakexow@mailinator.com", 'phoneNumber' => "+11948667447"], // array
        'merchantId'=>"MER_qZaVZ1645265780823HOaZW", // string
        'wayaPublicKey'=>"WAYAPUBK_TEST_0x3442f06c8fa6454e90c5b1a518758c70", // string
        'mode'=>"test" // string: \\test or live
      ]);
    } catch(\Pils36\Wayapay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    // store transaction reference so we can query in case user never comes back
    // perhaps due to network issue
    saveLastTransactionId($tranx->data->tranId);


    // Get Authorization URL to make payment to the Wayapay payment gateway environment
    $uri = $wayapay->authorizationUrl('test');  // change to live for production

    // Use the authorization url to
    $authorization_url = $uri.$tranx->data->tranId;

```

When the user enters their card details, Wayapay will validate and charge the card. It will do all the below:

Redirect back to a callback_url set when initializing the transaction or on your dashboard. Customers see a Transaction was successful message.


Before you give value to the customer, please make a server-side call to our verification endpoint to confirm the status and properties of the transaction.


### 3. Verify Transaction
After we redirect to your callback url, please verify the transaction before giving value.

```php
    $transactionId = isset($_GET['_tranId']) ? $_GET['_tranId'] : '';
    if(!$transactionId){
      die('No transaction id provided');
    }

    // initiate the Library's Wayapay Object
    $wayapay = new Pils36\Wayapay;
    try
    {
      // verify using the library
      $tranx = $wayapay->transaction->verify([
        '_tranId'=>$transactionId, // unique to transactions
        'mode'=>'test', // test or live
      ]);
    } catch(\Pils36\Wayapay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    if ($tranx->status === true) {
      // transaction was successful...
      // please check other things like whether you already gave value for this transactions
      // if the email matches the customer who owns the product etc
      // Save your transaction information
    }
```


## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Testing

``` bash
    $ composer test
```

## Contributing

Please see [CONTRIBUTING](.github/CONTRIBUTING.md) and [CONDUCT](.github/CONDUCT.md) for details. Check our [todo list](TODO.md) for features already intended.

## Security

If you discover any security related issues, please email adenugaadebambo41@gmail.com instead of using the issue tracker.

## Credits

- [Pils36][link-author]
- [All Contributors][link-contributors]


[link-author]: https://github.com/Pils36
[link-contributors]: ../../contributors
