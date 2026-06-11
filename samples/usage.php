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
use WayaPay\Webhook;
use WayaPay\Status\CollectionStatus;
use WayaPay\Status\PayoutStatus;
use WayaPay\Status\PayoutOutcome;

$client = new WayaPay([
    'merchantId' => getenv('WAYA_MERCHANT_ID') ?: '',
    'secretKey' => getenv('WAYA_SECRET_KEY') ?: '',
    // Optional: set so $client->webhooks can verify without an explicit secret arg.
    'webhookSecret' => getenv('WAYA_WEBHOOK_SECRET') ?: null,
    // Defaults to the production base URL; pass 'baseUrl' to override.
]);

try {
    // 1. Banks (GET — auto retried on transient failures).
    $banks = $client->payouts->listBanks();
    echo 'Banks: ' . count($banks) . PHP_EOL;

    // 2. Verify a destination before you ever move money.
    $verified = $client->payouts->verifyAccount([
        'accountNumber' => '0123456789',
        'bankCode' => '044',
    ]);
    echo 'Resolved name: ' . $verified['accountName'] . PHP_EOL;

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

    // 5b. Check payout status — reconcile by the reference you sent at initiation.
    $payoutRef = $payout['transactionReference'] ?? $payout['payoutReference'];
    $payoutStatus = $client->payouts->getStatus($payoutRef);
    switch (PayoutStatus::fromApi($payoutStatus['status'] ?? null)->outcome()) {
        case PayoutOutcome::Succeeded:
            echo 'Payout delivered.' . PHP_EOL;
            break;
        case PayoutOutcome::Reversed:
            echo 'Payout reversed — wallet re-credited.' . PHP_EOL;
            break;
        case PayoutOutcome::Reconciling:
            echo 'Payout still reconciling — check again later.' . PHP_EOL;
            break;
    }

    // 6. Create a payment link.
    $link = $client->collect->create([
        'paymentLinkName' => 'Order #1234',
        'description' => 'Order #1234 - 2 items',
        'payableAmount' => 1500,
        'redirectLink' => 'https://merchant.example.com/callback',
    ]);
    echo 'Send customer to: ' . $link['shortUrl'] . PHP_EOL;

    // 6b. Check collection status — the pull/safety-net path alongside the webhook.
    $collectRef = $link['transactionId'] ?? $link['paymentLinkReference'] ?? null;
    if ($collectRef) {
        $collectStatus = $client->collect->getStatus($collectRef);
        $parsed = CollectionStatus::fromApi($collectStatus['status'] ?? null);
        echo "Collection status: {$collectStatus['status']} (paid " . ($collectStatus['amountPaid'] ?? '0') . ')' . PHP_EOL;
        if ($parsed === CollectionStatus::Successful) {
            echo "Funds confirmed — fulfil order using refNo {$collectStatus['refNo']}" . PHP_EOL;
        }
    }

    // 7. Verify a webhook (offline demo). In production WayaPay POSTs this to your HTTPS endpoint;
    //    here we sign a sample body locally to show the verification flow end to end.
    $secret = 'WAYASECK_TEST_demo_webhook_secret';
    $body = '{"OrderId":"1779662251460508970","Amount":1500.00,"Fee":15.00,"Currency":"NGN",'
        . '"Status":"SUCCESSFUL","productName":"CARD","customer":{"email":"john@example.com"},'
        . '"merchantId":"MER_xyz","recurrentPayment":false}';
    $ts = (string) (int) (microtime(true) * 1000);
    $sig = base64_encode(hash_hmac('sha256', "$ts.$body", $secret, true));

    // Via the client wrapper. With 'webhookSecret' set on the client you can use
    // $client->webhooks->constructEvent($body, $ts, $sig) without the explicit secret.
    $evt = $client->webhooks->constructEventWith($body, $ts, $sig, $secret);
    echo "Webhook verified: {$evt['orderId']} — {$evt['status']} ({$evt['amount']} {$evt['currency']})" . PHP_EOL;
    if (Webhook::shouldFulfil($evt)) {
        echo "  Fulfil order — idempotency key {$evt['orderId']}" . PHP_EOL;
    }
} catch (WayaPayException $e) {
    fwrite(STDERR, "[{$e->type}] code={$e->errorCode} status={$e->status} :: {$e->getMessage()}" . PHP_EOL);
    exit(1);
}
