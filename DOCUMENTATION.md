# WayaQuick PHP SDK Documentation (v2.0.1)

Official integration guide for the **WayaQuick Merchant API v2** using the official PHP SDK.

---

## 1. Package Overview

* **Package Name**: [`wayaquick/payment-sdk`](https://packagist.org/packages/wayaquick/payment-sdk)
* **Current Version**: `2.0.1`
* **PHP Support**: PHP 8.1 or higher
* **Dependencies**: No heavy external dependencies (uses standard `ext-curl` and `ext-json`)
* **Namespace**: `WayaPay\`
* **Runnable Example**: [`samples/usage.php`](samples/usage.php)

### Installation

```bash
composer require wayaquick/payment-sdk
```

---

## 2. Initialization & Authentication

All API operations require your **Merchant ID** (`MER_...`) and **Secret Key** (`WAYASECK_TEST_...` for test sandbox, `WAYASECK_...` for live production):

```php
use WayaPay\WayaPay;

$client = new WayaPay([
    'merchantId' => getenv('WAYA_MERCHANT_ID'), // MER_...
    'secretKey'  => getenv('WAYA_SECRET_KEY'),  // WAYASECK_TEST_... or WAYASECK_...
    // Optional settings:
    // 'webhookSecret' => getenv('WAYA_WEBHOOK_SECRET'),
    // 'timeout'       => 30000, // milliseconds (30s)
    // 'maxRetries'    => 2,     // GET requests only
]);
```

> **Security Rule**: The Secret Key allows debiting your merchant wallet. Never expose this key in frontend code.

---

## 3. Core Features

### 3.1. Reference Generation (Idempotency)

Use the built-in reference helper to generate timestamped, collision-resistant identifiers:

```php
$ref = WayaPay::generateReference('PAYOUT');
// Produces: PAYOUT-1748160000000-A1B2C3D4
```

---

### 3.2. List Banks (`GET /get-bank-list`)

Fetch supported Nigerian banks and financial institutions with their CBN codes:

```php
$banks = $client->payouts->listBanks();
// Array of: [['code' => '044', 'name' => 'Access Bank', 'id' => '044', 'status' => true], ...]
foreach ($banks as $bank) {
    echo "{$bank['name']} ({$bank['code']})\n";
}
```

---

### 3.3. Account Verification / Name Enquiry (`POST /verify-account`)

Validate the bank account number and retrieve the registered account name:

```php
// Inter-bank enquiry (requires bankCode):
$account = $client->payouts->verifyAccount([
    'accountNumber' => '0123456789',
    'bankCode'      => '044',       // Access Bank
    'enquiryType'   => 'OTHERS',    // Default: 'OTHERS'
]);
echo "Account Name: " . $account['accountName'] . "\n";

// Intra-bank enquiry (Waya Bank to Waya Bank):
$wayaAccount = $client->payouts->verifyAccount([
    'accountNumber' => '0123456789',
    'enquiryType'   => 'WAYA-BANK', // bankCode is not required
]);
```

---

### 3.4. BVN Identity Verification (Two-Step OTP Flow)

In compliance with data privacy regulations, BVN verification uses a 2-step OTP flow:

```php
// Step 1: Initiate verification (sends SMS OTP to customer's BVN phone number)
$session = $client->identity->initiateBvn('22222222222'); // exactly 11 digits
echo "OTP sent to {$session['phoneNumber']}, Session Reference: {$session['reference']}\n";

// Step 2: Complete verification with the received OTP
$profile = $client->identity->completeBvn($session['reference'], '123456');
echo "Verified: {$profile['firstName']} {$profile['lastName']}\n";
echo "Watchlisted: {$profile['watchListed']}\n";
```

---

### 3.5. Disburse Payouts (`POST /payment-payout/initiate`)

Send payouts from your merchant balance to any bank account:

```php
use WayaPay\Status\PayoutStatus;
use WayaPay\Status\PayoutOutcome;

// 1. Submit payout request
$payout = $client->payouts->initiate([
    'amount'        => 25000,
    'currency'      => 'NGN',
    'accountNumber' => '0123456789',
    'bankCode'      => '044',
    'accountName'   => 'JOHN DOE',
    'reference'     => WayaPay::generateReference('PAYOUT'),
    'narration'     => 'Salary Disbursement',
]);

echo "Payout status: {$payout['status']}\n"; // "PROCESSING"

// 2. Reconcile payout status using reference:
$ref = $payout['merchantReference'] ?? $payout['payoutReference'];
$status = $client->payouts->getStatus($ref);

switch (PayoutStatus::fromApi($status['status'] ?? null)->outcome()) {
    case PayoutOutcome::Succeeded:
        echo "Funds delivered.\n";
        break;
    case PayoutOutcome::Reversed:
        echo "Transfer failed; wallet re-credited.\n";
        break;
    case PayoutOutcome::Reconciling:
        echo "Transfer is in flight; poll again later.\n";
        break;
}
```

---

### 3.6. Payment Collections / Hosted Checkout (`POST /payment-collect/initiate`)

Collect customer payments via WayaQuick Hosted Checkout:

```php
use WayaPay\Status\CollectionStatus;

// 1. Create Checkout Session
$checkout = $client->collect->create([
    'transactionId' => WayaPay::generateReference('COLL'),
    'amount'        => '1500.00',
    'currency'      => 'NGN',
    'firstName'     => 'John',
    'lastName'      => 'Doe',
    'email'         => 'customer@example.com',
    'phone'         => '08012345678',
    'description'   => 'Order #1001',
]);

echo "Redirect customer to checkout URL: {$checkout['checkOutUrl']}\n";

// 2. Check Deposit Status by transactionId
$collectStatus = $client->collect->getStatus($checkout['transactionId']);
if (CollectionStatus::fromApi($collectStatus['status'] ?? null)->isTerminal()) {
    echo "Collection status: {$collectStatus['status']} (Paid: {$collectStatus['amountPaid']})\n";
}
```

---

### 3.7. Inbound Webhook Verification

Verify HMAC-SHA256 signatures over raw inbound HTTP requests to prevent replay and forgery attacks:

```php
use WayaPay\WayaPayException;

// Read the exact raw body string from php://input
$rawBody = file_get_contents('php://input');
$timestamp = $_SERVER['HTTP_X_WAYA_TIMESTAMP'] ?? '';
$signature = $_SERVER['HTTP_X_WAYA_SIGNATURE'] ?? '';

try {
    $event = $client->webhooks->constructEvent(
        $rawBody,
        $timestamp,
        $signature,
        getenv('WAYA_SECRET_KEY')
    );

    if (($event['status'] ?? '') === 'SUCCESSFUL') {
        // Fulfil order keyed by $event['orderId']
        error_log("Payment verified for order: {$event['orderId']}");
    }

    http_response_code(200);
    echo "OK";
} catch (WayaPayException $e) {
    http_response_code(401);
    echo "Unauthorized: " . $e->getMessage();
}
```

---

## 4. Error Handling

Failed API operations throw `WayaPayException`:

```php
use WayaPay\WayaPayException;

try {
    $client->payouts->initiate($data);
} catch (WayaPayException $e) {
    echo "Error Type: {$e->type}\n";      // 'api' | 'validation' | 'network' | 'timeout'
    echo "API Code: {$e->errorCode}\n";   // e.g. "07"
    echo "HTTP Status: {$e->status}\n";   // e.g. 400, 401, 500
    echo "Message: " . $e->getMessage() . "\n";
}
```

---

## 5. Runnable Example Document

The SDK includes a complete runnable demonstration:

* **File Location**: [`samples/usage.php`](samples/usage.php)
* **Run Command**:
  ```bash
  WAYA_MERCHANT_ID=MER_... WAYA_SECRET_KEY=WAYASECK_TEST_... php samples/usage.php
  ```

---

## 6. Parseable AI Text (Copy & Paste for LLMs)

Copy the prompt block below to give any AI coding tool complete knowledge of how to use this library:

```xml
<wayaquick_php_sdk_context version="2.0.1">
  <system_role>
    You are an expert PHP backend engineer integrating the official WayaQuick PHP SDK (`wayaquick/payment-sdk`).
    Target PHP 8.1+. Adhere strictly to the v2 API contract and method signatures below.
  </system_role>

  <rules>
    1. INITIALIZATION: `new WayaPay(['merchantId' => ..., 'secretKey' => ...])`.
    2. BVN 2-STEP OTP: BVN verification requires `$client->identity->initiateBvn($bvn)` (sends OTP) followed by `$client->identity->completeBvn($reference, $otp)`. Single-step verification is deprecated.
    3. ACCOUNT ENQUIRY: `$client->payouts->verifyAccount(['accountNumber' => ..., 'bankCode' => ..., 'enquiryType' => ...])`. If `enquiryType == 'WAYA-BANK'`, `bankCode` is not required.
    4. HOSTED CHECKOUT: `$client->collect->create(['transactionId' => ..., 'amount' => ..., 'currency' => 'NGN', ...])`. Redirect customer to `$checkout['checkOutUrl']`.
    5. PAYOUTS ARE ASYNC: `$client->payouts->initiate(...)` returns status `PROCESSING`. Reconcile by polling `$client->payouts->getStatus($ref)` or via webhooks.
    6. WEBHOOK RAW BODY: `$client->webhooks->constructEvent($rawBody, $timestamp, $signature, $secret)`. Compute over raw body string (`file_get_contents('php://input')`).
  </rules>

  <package_info>
    <package_name>wayaquick/payment-sdk</package_name>
    <version>2.0.1</version>
    <install>composer require wayaquick/payment-sdk</install>
    <sample_file>samples/usage.php</sample_file>
  </package_info>

  <code_template>
    <![CDATA[
use WayaPay\WayaPay;

$client = new WayaPay([
    'merchantId' => getenv('WAYA_MERCHANT_ID'),
    'secretKey'  => getenv('WAYA_SECRET_KEY'),
]);

// 1. List Banks
$banks = $client->payouts->listBanks();

// 2. Account Name Enquiry
$verified = $client->payouts->verifyAccount([
    'accountNumber' => '0123456789',
    'bankCode'      => '044',
    'enquiryType'   => 'OTHERS',
]);

// 3. BVN 2-Step OTP
$session = $client->identity->initiateBvn('22222222222');
$profile = $client->identity->completeBvn($session['reference'], '123456');

// 4. Initiate Payout
$payout = $client->payouts->initiate([
    'amount'        => 5000,
    'accountNumber' => $verified['accountNumber'],
    'bankCode'      => '044',
    'accountName'   => $verified['accountName'],
    'reference'     => WayaPay::generateReference('PAYOUT'),
    'narration'     => 'Transfer payment',
]);
$payoutStatus = $client->payouts->getStatus($payout['merchantReference'] ?? $payout['payoutReference']);

// 5. Payment Collection
$checkout = $client->collect->create([
    'transactionId' => WayaPay::generateReference('COLL'),
    'amount'        => '1500.00',
    'currency'      => 'NGN',
    'firstName'     => 'John',
    'lastName'      => 'Doe',
    'email'         => 'customer@example.com',
    'phone'         => '08012345678',
    'description'   => 'Order #1001',
]);
// Redirect customer to: $checkout['checkOutUrl']
$collectStatus = $client->collect->getStatus($checkout['transactionId']);
    ]]>
  </code_template>
</wayaquick_php_sdk_context>
```

