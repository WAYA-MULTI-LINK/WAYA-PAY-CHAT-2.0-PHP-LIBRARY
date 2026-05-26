# WayaPay PHP SDK

Official PHP SDK for integrating with WayaPay payment APIs.

The SDK provides support for:

- Payment Collection
- Payouts
- Transaction Verification
- Bank Listing
- Account Verification

---

## Installation

Install the SDK using Composer:

```bash
composer require wayaquick-payment/php-sdk
```

If you are testing locally before publishing to Packagist, add the SDK manually to your project and run:

```bash
composer dump-autoload
```

---

## Requirements

- PHP >= 8.0
- PHP cURL extension enabled
- Composer

---

## Recommended Folder Structure

```text
wayapay-php-sdk/
├── src/
│   └── WayaPayRestClient.php
├── composer.json
└── README.md
```

---

## Initialization

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use WayaPay\WayaPayRestClient;

$client = new WayaPayRestClient(
    'your-merchant-id',
    'your-public-key',
    'development'
);
```

---

## Environment Values

| Environment | Description |
|---|---|
| `development` | Sandbox / staging environment |
| `test` | Sandbox / staging environment |
| `production` | Production environment |
| `prod` | Production environment |

---

## Initialize Payment

Initialize a payment collection request.

### Example

```php
$response = $client->initializePayment([
    'currency' => 'NGN',
    'amount' => 5000,
    'callBackUrl' => 'https://yourapp.com/payment/callback',
    'idempotencyKey' => uniqid('pay_', true),
    'paymentRef' => 'PAY-' . time(),

    'metadata' => [
        'firstName' => 'John',
        'lastName' => 'Doe',
        'phoneNumber' => '08012345678',
        'emailAddress' => 'john@example.com',
        'cancelUrl' => 'https://yourapp.com/payment/cancel'
    ]
]);

print_r($response);
```

### Request Parameters

| Field | Type | Required | Description |
|---|---|---|---|
| `currency` | string | Yes | ISO currency code, for example `NGN` |
| `amount` | int/float | Yes | Amount to charge |
| `callBackUrl` | string | Yes | URL the customer is redirected to after payment |
| `idempotencyKey` | string | Yes | Unique key to prevent duplicate transactions |
| `paymentRef` | string | Yes | Unique merchant payment reference |
| `metadata` | array | Yes | Customer information |

### Metadata Parameters

| Field | Type | Required | Description |
|---|---|---|---|
| `firstName` | string | Yes | Customer first name |
| `lastName` | string | Yes | Customer last name |
| `phoneNumber` | string | Yes | Customer phone number |
| `emailAddress` | string | Yes | Customer email address |
| `cancelUrl` | string | No | URL the customer is redirected to if payment is cancelled |

---

## Initiate Payout

Send funds from your merchant balance to a bank account.

> Always verify the destination account before initiating a payout.

### Example

```php
$response = $client->initiatePayout([
    'currency' => 'NGN',
    'amount' => 1000,
    'idempotencyKey' => uniqid('payout_', true),
    'bankCode' => '058',
    'accountNumber' => '0123456789'
]);

print_r($response);
```

### Request Parameters

| Field | Type | Required | Description |
|---|---|---|---|
| `currency` | string | Yes | ISO currency code |
| `amount` | int/float | Yes | Amount to send |
| `idempotencyKey` | string | Yes | Unique key to prevent duplicate payout attempts |
| `bankCode` | string | Yes | Destination bank code |
| `accountNumber` | string | Yes | Destination account number |

---

## Verify Transaction

Retrieve the current status of a transaction by reference.

### Example

```php
$response = $client->verifyTransaction('TRX-123456789');

print_r($response);
```

---

## Fetch Bank List

Retrieve the list of supported banks and their bank codes.

### Example

```php
$response = $client->fetchBankList();

print_r($response);
```

---

## Verify Account

Verify a bank account before initiating payout.

### Example

```php
$response = $client->verifyAccount([
    'accountNumber' => '0123456789',
    'bankCode' => '058'
]);

print_r($response);
```

---

## Successful Response Format

```json
{
  "status": true,
  "data": {
    "reference": "PAY-123456",
    "authorizationUrl": "https://checkout.url"
  }
}
```

---

## Error Response Format

```json
{
  "status": false,
  "message": "currency is required"
}
```

---

## Production Usage

Use environment variables for credentials in production.

```php
$client = new WayaPayRestClient(
    getenv('WAYAPAY_MERCHANT_ID'),
    getenv('WAYAPAY_SECRET_KEY'),
    'production'
);
```

---

## Example `.env`

```env
WAYAPAY_MERCHANT_ID=your-merchant-id
WAYAPAY_SECRET_KEY=your-secret-key
```

---

## Security Recommendations

- Never expose your API secret key in frontend code.
- Store credentials in environment variables.
- Always verify transactions server-side.
- Use unique idempotency keys for retries.
- Verify customer bank accounts before initiating payouts.

---

## Composer Configuration

Example `composer.json`:

```json
{
  "name": "wayapay/php-sdk",
  "description": "Official PHP SDK for WayaPay payment collection, payout, transaction verification, bank list, and account verification.",
  "type": "library",
  "license": "MIT",
  "autoload": {
    "psr-4": {
      "WayaPay\\": "src/"
    }
  },
  "require": {
    "php": ">=8.0",
    "ext-curl": "*"
  }
}
```

---

## Support

For support and integration assistance:

- Website: https://wayapay.ng
- Email: support@wayapay.ng

---

## License

MIT License
