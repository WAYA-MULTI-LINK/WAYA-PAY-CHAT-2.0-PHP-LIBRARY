<?php

declare(strict_types=1);

namespace WayaPay;

use WayaPay\Resources\Accounts;
use WayaPay\Resources\Banks;
use WayaPay\Resources\Collect;
use WayaPay\Resources\Identity;
use WayaPay\Resources\Payouts;
use WayaPay\Resources\Transactions;

/**
 * WayaPay Merchant API v2 client.
 *
 * Server side only. Your secret key lives here and only here.
 * Never ship it to a browser, a mobile app, or a public repo.
 */
final class WayaPay
{
    private const ENVIRONMENTS = [
        'staging' => 'https://services.staging.wayapay.ng/merchant-middleware/api/v2',
        'production' => 'https://services.wayapay.ng/merchant-middleware/api/v2',
    ];

    public readonly string $merchantId;
    public readonly string $secretKey;
    public readonly string $baseUrl;
    public readonly int $timeout;     // milliseconds
    public readonly int $maxRetries;  // GET only

    private readonly \Closure $transport;

    public readonly Banks $banks;
    public readonly Accounts $accounts;
    public readonly Identity $identity;
    public readonly Payouts $payouts;
    public readonly Collect $collect;
    public readonly Transactions $transactions;

    /**
     * @param array{
     *   merchantId: string,
     *   secretKey: string,
     *   environment?: 'staging'|'production',
     *   baseUrl?: string,
     *   timeout?: int,
     *   maxRetries?: int,
     *   transport?: callable
     * } $opts
     */
    public function __construct(array $opts)
    {
        $merchantId = $opts['merchantId'] ?? null;
        $secretKey = $opts['secretKey'] ?? null;
        if (!$merchantId) {
            throw new WayaPayException('merchantId is required', type: 'config');
        }
        if (!$secretKey) {
            throw new WayaPayException('secretKey is required', type: 'config');
        }

        $env = $opts['environment'] ?? 'production';
        $base = $opts['baseUrl'] ?? (self::ENVIRONMENTS[$env] ?? self::ENVIRONMENTS['production']);

        $this->merchantId = $merchantId;
        $this->secretKey = $secretKey;
        $this->baseUrl = rtrim($base, '/');
        $this->timeout = $opts['timeout'] ?? 30000;
        $this->maxRetries = $opts['maxRetries'] ?? 2;

        $transport = $opts['transport'] ?? null;
        $this->transport = $transport
            ? \Closure::fromCallable($transport)
            : \Closure::fromCallable([$this, 'curlTransport']);

        $this->banks = new Banks($this);
        $this->accounts = new Accounts($this);
        $this->identity = new Identity($this);
        $this->payouts = new Payouts($this);
        $this->collect = new Collect($this);
        $this->transactions = new Transactions($this);
    }

    /**
     * Low level request. Resources call this. Returns the envelope's `data`.
     *
     * @param  array<string,mixed>|null $body
     * @param  array<string,mixed>      $query
     * @return mixed
     */
    public function request(string $method, string $path, ?array $body = null, array $query = []): mixed
    {
        $url = $this->baseUrl . $path;
        if ($query) {
            $filtered = array_filter($query, static fn ($v) => $v !== null && $v !== '');
            if ($filtered) {
                $url .= '?' . http_build_query($filtered);
            }
        }

        $headers = [
            'X-Merchant-Id: ' . $this->merchantId,
            'Authorization: Bearer ' . $this->secretKey,
            'accept: application/json',
        ];

        $payload = null;
        if ($body !== null) {
            // Drop null entries so optional fields behave like the Node client (omit, not send null).
            $body = array_filter($body, static fn ($v) => $v !== null);
            $headers[] = 'Content-Type: application/json';
            $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $retryable = $method === 'GET';
        $ceiling = $retryable ? $this->maxRetries : 0;
        $attempt = 0;
        $lastErr = null;

        while ($attempt <= $ceiling) {
            try {
                [$status, $raw] = ($this->transport)($method, $url, $headers, $payload);

                $json = null;
                if ($raw !== '' && $raw !== null) {
                    $json = json_decode($raw, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        throw new WayaPayException(
                            "Non JSON response (HTTP {$status})",
                            status: $status,
                            raw: $raw,
                            type: 'api',
                        );
                    }
                }

                $failed = $status < 200 || $status >= 300
                    || (is_array($json) && ($json['success'] ?? null) === false);

                if ($failed) {
                    $transient = $status >= 500 || $status === 429;
                    if ($retryable && $transient && $attempt < $ceiling) {
                        $attempt++;
                        $this->backoff($attempt);
                        continue;
                    }
                    throw new WayaPayException(
                        is_array($json) ? ($json['message'] ?? "Request failed with HTTP {$status}") : "Request failed with HTTP {$status}",
                        errorCode: is_array($json) ? ($json['code'] ?? null) : null,
                        status: $status,
                        raw: $json ?? $raw,
                        type: 'api',
                    );
                }

                return is_array($json) ? ($json['data'] ?? null) : null;
            } catch (WayaPayException $e) {
                // API errors are final. No retry, just surface them.
                if ($e->type === 'api') {
                    throw $e;
                }
                $lastErr = $e;
                if ($retryable && $attempt < $ceiling) {
                    $attempt++;
                    $this->backoff($attempt);
                    continue;
                }
                throw $e;
            }
        }

        throw $lastErr ?? new WayaPayException('Request failed', type: 'network');
    }

    /**
     * Default cURL transport. Returns [int $status, string $body].
     * Throws WayaPayException of type 'timeout' or 'network' on transport failure.
     *
     * @param  array<int,string> $headers
     * @return array{0:int,1:string}
     */
    private function curlTransport(string $method, string $url, array $headers, ?string $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT_MS => $this->timeout,
            CURLOPT_CONNECTTIMEOUT_MS => $this->timeout,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno !== 0) {
            $type = $errno === CURLE_OPERATION_TIMEDOUT ? 'timeout' : 'network';
            throw new WayaPayException("Transport error: {$error}", type: $type, raw: $errno);
        }

        return [$status, $raw === false ? '' : (string) $raw];
    }

    private function backoff(int $attempt): void
    {
        $base = min(1000 * (2 ** ($attempt - 1)), 4000);
        usleep((int) (($base + random_int(0, 200)) * 1000));
    }

    /**
     * Validate required fields locally, before any network call.
     *
     * @param array<string,mixed> $payload
     * @param array<int,string>   $fields
     */
    public static function requireFields(array $payload, array $fields, string $context): void
    {
        $missing = [];
        foreach ($fields as $f) {
            $v = $payload[$f] ?? null;
            if ($v === null || $v === '') {
                $missing[] = $f;
            }
        }
        if ($missing) {
            throw new WayaPayException(
                "Missing required field(s) for {$context}: " . implode(', ', $missing),
                type: 'validation',
            );
        }
    }

    /**
     * Generate a unique reference. Your dedup and reconciliation key.
     * One per logical operation. Retries reuse it, new operations get a fresh one.
     */
    public static function generateReference(string $prefix = 'WP'): string
    {
        return sprintf(
            '%s-%d-%s',
            $prefix,
            (int) (microtime(true) * 1000),
            strtoupper(bin2hex(random_bytes(4))),
        );
    }
}
