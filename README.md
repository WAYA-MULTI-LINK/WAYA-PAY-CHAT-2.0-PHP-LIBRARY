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
`email`, `amount`, `description`, and `merchantId` are the most common compulsory parameters.

### 2. Initialize a transaction
Initialize a transaction by calling our API.

```php
    $wayapay = new \Pils36\Wayapay(SECRET_KEY);
    
    try
    {
      $tranx = $wayapay->transaction->initialize([
        'amount'=>$amount,     // string   
        'description'=>$description, // string
        'currency'=>$currency, // int
        'fee'=>$fee, // int
        'customer'=>json_encode(['name' => $name, 'email' => $email, 'phoneNumber' => $phoneNumber]), // json
        'merchantId'=>$merchantId, // string
        'wayaPublicKey'=>$wayaPublicKey // string
      ]);
    } catch(\Pils36\Wayapay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    // store transaction reference so we can query in case user never comes back
    // perhaps due to network issue
    save_last_transaction_reference($tranx->data->tranId);

    // redirect to page so User can pay
    header('Location: ' . $tranx->data->authorization_url);
```

When the user enters their card details, Wayapay will validate and charge the card. It will do all the below:

Redirect back to a callback_url set when initializing the transaction or on your dashboard. Customers see a Transaction was successful message.


Before you give value to the customer, please make a server-side call to our verification endpoint to confirm the status and properties of the transaction.


### 3. Verify Transaction
After we redirect to your callback url, please verify the transaction before giving value.

```php
    $reference = isset($_GET['reference']) ? $_GET['reference'] : '';
    if(!$reference){
      die('No reference supplied');
    }

    // initiate the Library's Wayapay Object
    $wayapay = new Pils36\Wayapay(SECRET_KEY);
    try
    {
      // verify using the library
      $tranx = $wayapay->transaction->verify([
        'reference'=>$reference, // unique to transactions
      ]);
    } catch(\Pils36\Wayapay\Exception\ApiException $e){
      print_r($e->getResponseObject());
      die($e->getMessage());
    }

    if ('success' === $tranx->data->status) {
      // transaction was successful...
      // please check other things like whether you already gave value for this ref
      // if the email matches the customer who owns the product etc
      // Give value
    }
```

### MetadataBuilder

This class helps you build valid json metadata strings to be sent when making transaction requests.
```php
    $builder = new MetadataBuilder();
```


#### Add Custom Fields

Add Custom Fields by calling the `withCustomField` function (These will shown on dashboard).

```php
    $builder->withCustomField('Mobile Number', '08123456789');
    $builder->withCustomField('Description', 'Chief, we move!');
```

#### Build JSON

Finally call `build()` to get your JSON metadata string.


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
