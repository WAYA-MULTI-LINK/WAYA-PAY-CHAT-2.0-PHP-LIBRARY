<?php

namespace WayaPay;

use Exception;

class WayaPayRestClient
{
    private string $merchantId;
    private string $publicKey;
    private string $baseUrl;
    private string $defaultPaymentLink;

    private const API_BASE = [
        'test' => 'https://services.staging.wayapay.ng',
        'prod' => 'https://services.wayapay.ng',
    ];

    private const PAYMENT_LINK = [
        'test' => 'https://pay.staging.wayapay.ng/?_tranId=',
        'prod' => 'https://pay.wayapay.ng/?_tranId=',
    ];

    public function __construct(string $merchantId, string $publicKey, string $environment)
    {
        if (empty($merchantId) || empty($publicKey) || empty($environment)) {
            throw new Exception('merchantId, publicKey, and environment are required');
        }

        $isProd = in_array(strtolower(trim($environment)), ['production', 'prod']);

        $this->merchantId = $merchantId;
        $this->publicKey = $publicKey;
        $this->baseUrl = $isProd ? self::API_BASE['prod'] : self::API_BASE['test'];
        $this->defaultPaymentLink = $isProd ? self::PAYMENT_LINK['prod'] : self::PAYMENT_LINK['test'];
    }

    public function initializePayment(array $payload): array
    {
        $required = [
            'currency',
            'amount',
            'callBackUrl',
            'idempotencyKey',
            'paymentRef',
            'metadata'
        ];

        foreach ($required as $field) {
            if (empty($payload[$field])) {
                return ['status' => false, 'message' => "{$field} is required"];
            }
        }

        $metadataRequired = [
            'firstName',
            'lastName',
            'phoneNumber',
            'emailAddress'
        ];

        foreach ($metadataRequired as $field) {
            if (empty($payload['metadata'][$field])) {
                return ['status' => false, 'message' => "metadata.{$field} is required"];
            }
        }

        $response = $this->request(
            'POST',
            '/payment-collect/initiate',
            $payload
        );

        return [
            'status' => true,
            'data' => $response['data'] ?? $response
        ];
    }

    public function initiatePayout(array $payload): array
    {
        $required = [
            'currency',
            'amount',
            'idempotencyKey',
            'bankCode',
            'accountNumber'
        ];

        foreach ($required as $field) {
            if (empty($payload[$field])) {
                return ['status' => false, 'message' => "{$field} is required"];
            }
        }

        $response = $this->request(
            'POST',
            '/payment-payout/initiate',
            $payload
        );

        return [
            'status' => true,
            'data' => $response
        ];
    }

    public function verifyTransaction(string $transactionRef): array
    {
        if (empty($transactionRef)) {
            return ['status' => false, 'message' => 'transactionRef is required'];
        }

        $response = $this->request(
            'GET',
            '/payment/transaction?ref=' . urlencode($transactionRef)
        );

        return [
            'status' => true,
            'data' => $response['data'] ?? $response
        ];
    }

    public function fetchBankList(): array
    {
        $response = $this->request('GET', '/banks-list');

        return [
            'status' => true,
            'data' => $response['data'] ?? $response
        ];
    }

    public function verifyAccount(array $payload): array
    {
        if (empty($payload['accountNumber'])) {
            return ['status' => false, 'message' => 'accountNumber is required'];
        }

        if (empty($payload['bankCode'])) {
            return ['status' => false, 'message' => 'bankCode is required'];
        }

        $response = $this->request(
            'GET',
            '/account-verification',
            $payload
        );

        return [
            'status' => true,
            'data' => $response['data'] ?? $response
        ];
    }

    private function request(string $method, string $endpoint, array $body = []): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Content-Type: application/json',
            'Merchant-ID: ' . $this->merchantId,
            'API-Secret-Key: ' . $this->publicKey,
        ];

        $curl = curl_init();

        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
        ];

        if (!empty($body)) {
            $options[CURLOPT_POSTFIELDS] = json_encode($body);
        }

        curl_setopt_array($curl, $options);

        $response = curl_exec($curl);
        $error = curl_error($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        curl_close($curl);

        if ($error) {
            return [
                'status' => false,
                'message' => $error
            ];
        }

        $decodedResponse = json_decode($response, true);

        if ($statusCode >= 400) {
            return $decodedResponse ?: [
                'status' => false,
                'message' => 'Request failed',
                'code' => $statusCode
            ];
        }

        return $decodedResponse ?: [];
    }
}