# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org).

## [2.0.0] - 2026-06-06

A PHP client for the WayaPay Merchant API v2, restructured into a proper
Composer package (`src/`, `tests/`, `samples/`) with a full test suite. No
dependencies outside `ext-curl` and `ext-json`.

### Added

- `WayaPay` client constructed from an options array (`merchantId`, `secretKey`, `baseUrl`, `timeout`, `maxRetries`, `transport`). Defaults to the production base URL.
- Four resources mirroring the .NET library: `payouts`, `collect`, `identity`, `webhooks`.
- `payouts->listBanks()` — returns all supported banks and their CBN codes.
- `payouts->verifyAccount()` — resolves an account number to its registered name; requires `bankCode` unless `enquiryType` is `WAYABANK`.
- `payouts->initiate()` — initiates a bank transfer; defaults `currency` to `NGN` and auto-generates `reference`; `PROCESSING` means accepted, not settled.
- `payouts->getStatus()` — returns the latest status of a payout by the reference you sent at initiation.
- `collect->create()` — creates a payment link; defaults a one-time NGN link; requires `expiryDate` when `linkCanExpire` is true.
- `collect->getStatus()` — returns the current state of a deposit by its `refNo`.
- `identity->verifyBvn()` — verifies a BVN with a local 11-digit check before the network call; accepts a string or `['bvn' => ...]`.
- `webhooks->constructEvent()` / `webhooks->verifySignature()` — verify and parse inbound transaction webhooks.
- `WayaPay::generateReference()` — timestamped, collision-resistant idempotency key.
- `WayaPay::requireFields()` — local required-field validation that throws before any network call.
- Automatic retry with exponential backoff on GET requests (timeouts, network errors, 429, 5xx); writes never auto-retry.
- Injectable `transport` callable for testing without the network.
- `WayaPayException` carrying `type`, `errorCode`, `status`, and `raw`.
- PHPUnit test suite driven by stub/capturing transports, plus a `live` group of integration tests excluded from the default run.

### Changed from 1.x

- Source moved from the package root into `src/` under the `WayaPay\` namespace; resources live in `WayaPay\Resources\`.
- The example moved to `samples/usage.php`.
