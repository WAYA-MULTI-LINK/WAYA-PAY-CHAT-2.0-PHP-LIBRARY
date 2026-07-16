# WayaPay PHP

PHP client for the **WayaPay Merchant API v2**. Collect payments, send payouts, verify bank accounts, and run BVN identity checks in Nigeria.

One client, four resources, a single transport that handles auth headers and the shared response envelope so you never parse `success`/`code` by hand. No Guzzle, no PSR-18 stack — just `ext-curl` and `ext-json`. **Server-side only** — your secret key must never leave your server.

## Requirements

PHP 8.1 or newer, with the `curl` and `json` extensions (both standard).

## Install

```bash
composer require wayaquick/payment-sdk
```

Or drop the folder in and point any PSR-4 autoloader at `WayaPay\` => `src/`.

## Quickstart

```php
use WayaPay\WayaPay;

$client = new WayaPay([
    'merchantId' => getenv('WAYA_MERCHANT_ID'),  // MER_...
    'secretKey'  => getenv('WAYA_SECRET_KEY'),   // WAYASECK_TEST_... or WAYASECK_...
]);
```

The client targets the production base URL. Test with a `WAYASECK_TEST_...` key, then swap in your live `WAYASECK_...` key when ready — the rest of your code stays the same. Pass `baseUrl` to point at a different host.

## What you get back

Every method returns the envelope's `data` payload directly, already decoded into an associative array. The `success`, `code`, and `timestamp` fields only matter when something fails — and failures throw — so the happy path stays clean:

```php
$acct = $client->payouts->verifyAccount(['accountNumber' => '0123456789', 'bankCode' => '044']);
echo $acct['accountName']; // straight to the useful part
```

## List banks

```php
$banks = $client->payouts->listBanks();
// [['code' => '044', 'name' => 'Access Bank', 'id' => '044', 'status' => true], ...]
```

## Verify an account

Always verify before sending a payout — confirms the account exists and returns the registered name.

```php
$result = $client->payouts->verifyAccount([
    'accountNumber' => '0123456789',
    'bankCode'      => '044',       // omit only when enquiryType is 'WAYABANK'
    'enquiryType'   => 'OTHERS',    // default
]);
echo $result['accountName']; // "JOHN DOE"
```

## Initiate a payout

```php
$payout = $client->payouts->initiate([
    'amount'        => 25000,
    'accountNumber' => '0123456789',
    'bankCode'      => '058',
    'accountName'   => 'JOHN DOE',  // match the verified name
    'narration'     => 'April salary',
    // currency defaults to 'NGN', reference auto-generated if omitted
]);
// $payout['status'] === 'PROCESSING' means accepted, not settled

// Reconcile by the reference you sent at initiation:
$status = $client->payouts->getStatus($payout['transactionReference'] ?? $payout['payoutReference']);
// interpret $status['status'] with WayaPay\Status\PayoutStatus::fromApi(...)
```

## Collect a payment

```php
$link = $client->collect->create([
    'paymentLinkName' => 'Order #1234',
    'description'     => 'Order #1234 - 2 items',
    'payableAmount'   => 1500,
    'redirectLink'    => 'https://merchant.example.com/callback',
    // paymentLinkType defaults to 'ONE_TIME_PAYMENT_LINK', currency to 'NGN'
]);
// Send the customer to $link['shortUrl']. Keep $link['paymentLinkReference'] to reconcile.

// Reconcile a deposit by its refNo (the gateway transactionId / webhook orderId):
$collectStatus = $client->collect->getStatus($refNo);
// interpret $collectStatus['status'] with WayaPay\Status\CollectionStatus::fromApi(...)
```

If you set `'linkCanExpire' => true`, you must also pass `'expiryDate'`. The library enforces it before the call leaves your server. `collect->create` also fails unless you have whitelisted your server IPs and configured payment preferences on the dashboard.

## BVN identity check

```php
$bvn = $client->identity->verifyBvn('22212345678'); // 11 digits, validated locally
echo "{$bvn['firstName']} {$bvn['lastName']}";
// treat anything other than "False" on $bvn['watchListed'] with care
```

BVN data is sensitive personal information. Store, transmit, and log it only as your data-protection obligations allow.

A payout returning `PROCESSING` is accepted, not settled. Poll `payouts->getStatus` with the reference until it reaches a terminal status.

## The resources

| Resource            | Method                               | Endpoint                                 |
| ------------------- | ------------------------------------ | ---------------------------------------- |
| `$client->payouts`  | `listBanks`                          | `GET /get-bank-list`                     |
| `$client->payouts`  | `verifyAccount`                      | `POST /verify-account`                   |
| `$client->payouts`  | `initiate`                           | `POST /payment-payout/initiate`          |
| `$client->payouts`  | `getStatus`                          | `GET /payment-payout/status/{reference}` |
| `$client->collect`  | `create`                             | `POST /payment-collect/initiate`         |
| `$client->collect`  | `getStatus`                          | `GET /payment-collect/status/{refNo}`    |
| `$client->identity` | `verifyBvn`                          | `POST /identity-verification/bvn`        |
| `$client->webhooks` | `constructEvent` / `verifySignature` | — (verifies inbound webhooks)            |

## References

In v2, the unique `reference` you supply is your dedup and reconciliation key. Generate a fresh one per logical operation so retries map to the original record instead of spawning duplicates. The library auto-fills it on payouts when you leave it out, or generate your own:

```php
$ref = WayaPay::generateReference('PAYOUT'); // PAYOUT-1748160000000-A1B2C3D4
```

## Errors

Everything that fails throws a `WayaPayException`. Branch on `type` for the category and `errorCode` for the WayaPay code. (It is `errorCode`, not `code`, because PHP's base `Exception` already owns `getCode()` and that one is an int.)

```php
use WayaPay\WayaPayException;

try {
    $client->payouts->initiate([/* ... */]);
} catch (WayaPayException $e) {
    $e->type;         // 'api' | 'validation' | 'network' | 'timeout' | 'config'
    $e->errorCode;    // WayaPay code, e.g. "07". null when not an API error.
    $e->status;       // HTTP status when known
    $e->getMessage(); // human readable
    $e->raw;          // raw body or underlying error, for your logs
}
```

Validation errors fire **before** any network call, so a missing field or a malformed BVN never burns a request.

## Timeouts and retries

Configurable on the constructor:

```php
new WayaPay([
    'merchantId' => '...', 'secretKey' => '...',
    'timeout'    => 30000,   // milliseconds
    'maxRetries' => 2,
]);
```

Retries apply to **GET only** (bank list, status checks) and only on timeouts, network errors, 429, or 5xx, with exponential backoff. Writes (payout, account verify, collect, BVN) never auto-retry, because retrying a write you are unsure about is how you pay someone twice. Retry those yourself, with the same `reference`, once you have checked the transaction status.

## Custom transport (testing)

The constructor accepts a `transport` callable so you can test without touching the network. Signature: `function (string $method, string $url, array $headers, ?string $body): array` returning `[int $status, string $rawBody]`. Throw a `WayaPayException` of type `network` or `timeout` to simulate transport failures.

```php
$client = new WayaPay([
    'merchantId' => 'm', 'secretKey' => 's',
    'transport' => fn ($method, $url, $headers, $body) => [200, json_encode([
        'success' => true, 'code' => '00', 'data' => [/* ... */],
    ])],
]);
```

This is exactly how the test suite runs — see [tests/](tests/).

## Full example

See [samples/usage.php](samples/usage.php) for a runnable end-to-end demo covering every resource.

```bash
WAYA_MERCHANT_ID=MER_... WAYA_SECRET_KEY=WAYASECK_TEST_... php samples/usage.php
```

## Before you go live

On the merchant dashboard: finish KYC, grab your Merchant ID, generate your secret key under **Settings → API Keys and Webhooks**, whitelist your server IPs, and configure payment preferences. Payment Collect refuses to work until the last two are done. Then swap your `WAYASECK_TEST_...` key for the live `WAYASECK_...` key — the rest of your code stays the same.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT
