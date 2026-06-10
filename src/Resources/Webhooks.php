<?php

declare(strict_types=1);

namespace WayaPay\Resources;

use WayaPay\Webhook;
use WayaPay\WayaPayException;

/**
 * Verifies and parses incoming transaction webhooks. A thin, discoverable wrapper over the static
 * {@see Webhook} helper — the methods without a $secret argument use the secret configured on the
 * client (WayaPay 'webhookSecret' option); the ones with it let you route per environment.
 */
final class Webhooks
{
    public function __construct(private readonly ?string $secret = null)
    {
    }

    /**
     * Verifies the signature and replay window using the configured webhook secret, then parses
     * the body. Throws {@see \WayaPay\WayaPayWebhookException} if verification fails.
     *
     * @return array<string,mixed> A normalized webhook event array. See {@see Webhook::constructEvent()}.
     */
    public function constructEvent(string $payload, ?string $timestamp, ?string $signature, ?int $toleranceSeconds = null): array
    {
        return Webhook::constructEvent($payload, $timestamp, $signature, $this->requireSecret(), $toleranceSeconds);
    }

    /**
     * Same as {@see Webhooks::constructEvent()} but with an explicit secret (e.g. to route TEST vs PRODUCTION).
     *
     * @return array<string,mixed> A normalized webhook event array. See {@see Webhook::constructEvent()}.
     */
    public function constructEventWith(string $payload, ?string $timestamp, ?string $signature, string $secret, ?int $toleranceSeconds = null): array
    {
        return Webhook::constructEvent($payload, $timestamp, $signature, $secret, $toleranceSeconds);
    }

    /** Signature-only check (no replay window) using the configured webhook secret. */
    public function verifySignature(string $payload, ?string $timestamp, ?string $signature): bool
    {
        return Webhook::verifySignature($payload, $timestamp, $signature, $this->requireSecret());
    }

    /** Signature-only check (no replay window) with an explicit secret. */
    public function verifySignatureWith(string $payload, ?string $timestamp, ?string $signature, string $secret): bool
    {
        return Webhook::verifySignature($payload, $timestamp, $signature, $secret);
    }

    private function requireSecret(): string
    {
        if ($this->secret === null || $this->secret === '') {
            throw new WayaPayException(
                'No webhook secret configured. Set the "webhookSecret" option on the WayaPay client, '
                . 'or call the *With() method that takes an explicit secret.',
                type: 'config',
            );
        }

        return $this->secret;
    }
}
