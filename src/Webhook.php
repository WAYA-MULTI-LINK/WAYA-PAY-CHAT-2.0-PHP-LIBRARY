<?php

declare(strict_types=1);

namespace WayaPay;

use WayaPay\Status\WebhookStatus;

/**
 * Verifies and parses WayaPay transaction webhooks. Signature verification needs no network
 * call, so this is a standalone static helper — pass the raw request body, the signature
 * headers, and the merchant secret for the event's environment.
 *
 * CRITICAL: verify every webhook before acting on it. An unsigned or wrongly-signed call is
 * hostile — {@see Webhook::constructEvent()} throws {@see WayaPayWebhookException} rather than
 * returning a value.
 *
 * The secret is your merchantSecretTestKey for a TEST transaction or your
 * merchantProductionSecretKey for a PRODUCTION one. Most merchants keep one verifier per
 * environment and route by which key validates.
 *
 * Capture the EXACT raw request body before any JSON parsing. If your framework deserialises
 * and re-serialises the body, the recomputed HMAC will not match.
 *
 * Crypto contract:
 *   signature = base64_encode(hash_hmac('sha256', "{timestamp}.{payload}", $secret, true))
 */
final class Webhook
{
    /** Header carrying the epoch-millisecond timestamp that is signed alongside the body. */
    public const TIMESTAMP_HEADER = 'X-Waya-Timestamp';

    /** Header carrying the Base64 HMAC-SHA256 signature. */
    public const SIGNATURE_HEADER = 'X-Waya-Signature';

    /** Default replay-protection window, in seconds. Webhooks older or newer than this are rejected. */
    public const DEFAULT_TOLERANCE_SECONDS = 300;

    /**
     * Low-level signature check: returns true when $signature equals
     * base64_encode(HMAC-SHA256("{timestamp}.{payload}", secret)). Does NOT check the replay
     * window — prefer {@see Webhook::constructEvent()}. Comparison is constant-time. Never throws.
     */
    public static function verifySignature(string $payload, ?string $timestamp, ?string $signature, string $secret): bool
    {
        if ($timestamp === null || $timestamp === ''
            || $signature === null || $signature === ''
            || $secret === '') {
            return false;
        }

        $expected = base64_encode(hash_hmac('sha256', $timestamp . '.' . $payload, $secret, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Verifies the signature and replay window, then parses the body into a normalized event array.
     * Throws {@see WayaPayWebhookException} if verification fails — never returns an unverified event.
     *
     * @param string      $payload          The exact raw request body, as text.
     * @param string|null $timestamp        Value of the {@see Webhook::TIMESTAMP_HEADER} header (epoch milliseconds).
     * @param string|null $signature        Value of the {@see Webhook::SIGNATURE_HEADER} header (Base64 HMAC-SHA256).
     * @param string      $secret           The merchant secret for this event's environment (TEST or PRODUCTION).
     * @param int|null    $toleranceSeconds Replay window in seconds. Defaults to 300. Pass a negative value
     *                                       (e.g. -1) to skip the timestamp check (not recommended outside tests).
     *
     * @return array{
     *   orderId:string,
     *   amount:float,
     *   description:?string,
     *   fee:float,
     *   currency:?string,
     *   status:string,
     *   tranTime:?string,
     *   transactionDate:?string,
     *   productName:?string,
     *   businessName:?string,
     *   customer:?array{name:?string,email:?string,phoneNumber:?string,customerId:?string},
     *   merchantId:?string,
     *   branchCategory:?string,
     *   recurrentPayment:bool
     * }
     *
     * @throws WayaPayWebhookException
     */
    public static function constructEvent(
        string $payload,
        ?string $timestamp,
        ?string $signature,
        string $secret,
        ?int $toleranceSeconds = null,
    ): array {
        if ($secret === '') {
            throw new WayaPayWebhookException('Merchant secret is required.');
        }

        if (!self::verifySignature($payload, $timestamp, $signature, $secret)) {
            throw new WayaPayWebhookException('Webhook signature verification failed.');
        }

        $tolerance = $toleranceSeconds ?? self::DEFAULT_TOLERANCE_SECONDS;
        if ($tolerance >= 0) {
            if ($timestamp === null || !is_numeric($timestamp)) {
                throw new WayaPayWebhookException('Webhook timestamp is not a valid epoch-millisecond value.');
            }
            $tsMs = (int) $timestamp;
            $nowMs = (int) (microtime(true) * 1000);
            if (abs($nowMs - $tsMs) > $tolerance * 1000) {
                throw new WayaPayWebhookException(
                    "Webhook timestamp is outside the {$tolerance}s tolerance window (possible replay).",
                );
            }
        }

        $raw = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($raw)) {
            throw new WayaPayWebhookException('Webhook body is not valid JSON.');
        }

        return self::normalize($raw);
    }

    /**
     * True only when the event's status is SUCCESSFUL — safe to fulfil the order
     * (after an idempotency check on the orderId).
     *
     * @param array<string,mixed> $event A normalized event array from {@see Webhook::constructEvent()}.
     */
    public static function shouldFulfil(array $event): bool
    {
        return WebhookStatus::fromApi(isset($event['status']) ? (string) $event['status'] : null)->shouldFulfil();
    }

    /**
     * Normalize the tolerant wire body into a stable associative array. The contract mixes
     * casing: OrderId/Amount/Description/Fee/Currency/Status/TranTime/TransactionDate arrive
     * PascalCase, while productName/businessName/customer/merchantId/branchCategory/recurrentPayment
     * arrive camelCase. Read both casings so either binds.
     *
     * @param  array<string,mixed> $raw
     * @return array<string,mixed>
     */
    private static function normalize(array $raw): array
    {
        $customer = $raw['customer'] ?? $raw['Customer'] ?? null;

        return [
            'orderId' => (string) ($raw['OrderId'] ?? $raw['orderId'] ?? ''),
            'amount' => (float) ($raw['Amount'] ?? $raw['amount'] ?? 0),
            'description' => self::nullableString($raw['Description'] ?? $raw['description'] ?? null),
            'fee' => (float) ($raw['Fee'] ?? $raw['fee'] ?? 0),
            'currency' => self::nullableString($raw['Currency'] ?? $raw['currency'] ?? null),
            'status' => (string) ($raw['Status'] ?? $raw['status'] ?? ''),
            'tranTime' => self::nullableString($raw['TranTime'] ?? $raw['tranTime'] ?? null),
            'transactionDate' => self::nullableString($raw['TransactionDate'] ?? $raw['transactionDate'] ?? null),
            'productName' => self::nullableString($raw['productName'] ?? $raw['ProductName'] ?? null),
            'businessName' => self::nullableString($raw['businessName'] ?? $raw['BusinessName'] ?? null),
            'customer' => is_array($customer) ? self::normalizeCustomer($customer) : null,
            'merchantId' => self::nullableString($raw['merchantId'] ?? $raw['MerchantId'] ?? null),
            'branchCategory' => self::nullableString($raw['branchCategory'] ?? $raw['BranchCategory'] ?? null),
            'recurrentPayment' => (bool) ($raw['recurrentPayment'] ?? $raw['RecurrentPayment'] ?? false),
        ];
    }

    /**
     * @param  array<string,mixed> $customer
     * @return array{name:?string,email:?string,phoneNumber:?string,customerId:?string}
     */
    private static function normalizeCustomer(array $customer): array
    {
        return [
            'name' => self::nullableString($customer['name'] ?? $customer['Name'] ?? null),
            'email' => self::nullableString($customer['email'] ?? $customer['Email'] ?? null),
            'phoneNumber' => self::nullableString($customer['phoneNumber'] ?? $customer['PhoneNumber'] ?? null),
            'customerId' => self::nullableString($customer['customerId'] ?? $customer['CustomerId'] ?? null),
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return $value === null ? null : (string) $value;
    }
}
