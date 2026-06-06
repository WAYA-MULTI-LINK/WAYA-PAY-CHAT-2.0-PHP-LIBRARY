<?php

declare(strict_types=1);

// Runnable end-to-end demo. Run with:
//   WAYA_MERCHANT_ID=MER_... WAYA_SECRET_KEY=WAYASECK_TEST_... php samples/usage.php
//
// Uses Composer's autoloader when installed, otherwise a minimal PSR-4 fallback.

$composer = __DIR__ . '/../vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
} else {
    spl_autoload_register(static function (string $class): void {
        if (str_starts_with($class, 'WayaPay\\')) {
            $rel = str_replace('\\', '/', substr($class, strlen('WayaPay\\')));
            $file = __DIR__ . '/../src/' . $rel . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

use WayaPay\WayaPay;
use WayaPay\WayaPayException;

$client = new WayaPay([
    'merchantId' => getenv('WAYA_MERCHANT_ID') ?: '',
    'secretKey' => getenv('WAYA_SECRET_KEY') ?: '',
    'environment' => 'staging', // flip to 'production' when steady
]);

try {
    // 1. Banks (GET — auto retried on transient failures).
    $banks = $client->banks->list();
    echo 'Banks: ' . count($banks) . PHP_EOL;

    // 2. Verify a destination before you ever move money.
    $verified = $client->accounts->verify([
        'accountNumber' => '0123456789',
        'bankCode' => '044',
    ]);
    echo 'Resolved name: ' . $verified['accountName'] . PHP_EOL;

    // 3. Mint a virtual account for an order.
    $vacct = $client->accounts->createDynamic([
        'accountName' => 'ORDER-7821 PAYMENT',
        'customerId' => 'CUST-98765',
        'referenceId' => 'ORDER-7821',
        'purpose' => 'Order payment',
    ]);
    echo 'Pay into: ' . $vacct['virtualAccountNumber'] . PHP_EOL;

    // 4. BVN check.
    $bvn = $client->identity->verifyBvn('22212345678');
    echo "BVN holder: {$bvn['firstName']} {$bvn['lastName']} | watchListed: {$bvn['watchListed']}" . PHP_EOL;

    // 5. Pay someone out. Verify the name above first.
    $payout = $client->payouts->initiate([
        'amount' => 25000,
        'accountNumber' => $verified['accountNumber'],
        'bankCode' => '058',
        'accountName' => $verified['accountName'],
        'reference' => WayaPay::generateReference('PAYOUT'),
        'narration' => 'Salary payment',
    ]);
    echo "Payout: {$payout['payoutReference']} {$payout['status']}" . PHP_EOL;

    // 6. Create a payment link.
    $link = $client->collect->create([
        'paymentLinkName' => 'Order #1234',
        'description' => 'Order #1234 - 2 items',
        'payableAmount' => 1500,
        'redirectLink' => 'https://merchant.example.com/callback',
    ]);
    echo 'Send customer to: ' . $link['shortUrl'] . PHP_EOL;

    // 7. Verify a transaction. Trust status, not your own assumptions.
    $txn = $client->transactions->verify($payout['payoutReference']);
    echo 'Txn status: ' . $txn['status'] . PHP_EOL;

    // 8. Reconcile every successful transaction in a window, one lazy stream.
    $count = 0;
    foreach ($client->transactions->historyAll([
        'status' => 'SUCCESS',
        'from' => '2026-05-01T00:00:00Z',
        'to' => '2026-05-31T23:59:59Z',
    ]) as $t) {
        $count++;
    }
    echo "Reconciled: {$count} transactions" . PHP_EOL;
} catch (WayaPayException $e) {
    fwrite(STDERR, "[{$e->type}] code={$e->errorCode} status={$e->status} :: {$e->getMessage()}" . PHP_EOL);
    exit(1);
}
