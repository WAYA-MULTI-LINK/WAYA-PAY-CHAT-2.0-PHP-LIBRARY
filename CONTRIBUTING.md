# Contributing

## Requirements

- PHP 8.1 or newer (`php --version`) with `ext-curl` and `ext-json`
- [Composer](https://getcomposer.org)

## Project layout

```
src/
  WayaPay.php               # Entry point — transport, retry loop, auth headers, helpers
  WayaPayException.php       # Single exception type; branch on ->type and ->errorCode
  Resources/
    Payouts.php              # payouts->listBanks, verifyAccount, initiate, getStatus
    Collect.php              # collect->create, getStatus
    Identity.php             # identity->verifyBvn
    Webhooks.php             # webhooks->constructEvent, verifySignature

tests/
  bootstrap.php              # Composer autoload, with a PSR-4 fallback
  Support/
    Factory.php              # Builds clients backed by stub transports + request fixtures
    CapturingTransport.php   # Records the last request — assert method/url/headers/body
    SequenceTransport.php    # Returns a queue of responses — drives retry/pagination tests
  ClientTest.php             # Construction, headers, envelope, errors, retry, helpers
  PayoutsTest.php            # ... one file per resource
  IdentityTest.php
  CollectTest.php
  WebhookTest.php
  Live/
    LiveTest.php             # #[Group('live')] — hits the real API, excluded by default

samples/
  usage.php                 # Runnable end-to-end demo — kept in sync with the API
```

The package is PSR-4: `WayaPay\` => `src/`, `WayaPay\Tests\` => `tests/`.

## Install dependencies

```bash
composer install
```

## Run unit tests

```bash
# All unit tests (no network, no credentials)
composer test
# or
vendor/bin/phpunit

# A single file or filter
vendor/bin/phpunit tests/PayoutsTest.php
vendor/bin/phpunit --filter testInitiateDefaultsCurrencyAndReference
```

Unit tests run entirely against injected `transport` callables (see `tests/Support`). No credentials, no network.

## Run live integration tests

Live tests are in the `live` group and excluded from the default run. They call
the real WayaPay API, so you need valid credentials.

```bash
export WAYA_MERCHANT_ID=MER_...
export WAYA_SECRET_KEY=WAYASECK_TEST_...
# live tests run against the production API; use test credentials

vendor/bin/phpunit --group live
```

Live tests are intentionally not run in CI to avoid flakiness from network
conditions or credential availability.

## Run the sample

```bash
WAYA_MERCHANT_ID=MER_... WAYA_SECRET_KEY=WAYASECK_TEST_... php samples/usage.php
```

## Adding a new feature

1. Add the method to the relevant resource under `src/Resources/`.
2. Validate required fields with `WayaPay::requireFields(...)` before the network call.
3. Add unit tests covering the happy path, validation/error path, correct HTTP method/path, and request body shape — drive them with a `CapturingTransport`.
4. Update `samples/usage.php` if the feature is user-facing.
5. Update `CHANGELOG.md` under the relevant version.

## Versioning

This project follows [Semantic Versioning](https://semver.org).

If you are publishing an updated package version, change the version number first, then create and push the release tag:

```bash
git tag v2.0.0
git push origin v2.0.0
```

Replace `v2.0.0` with the new version you are releasing.

## Code style

- `declare(strict_types=1);` at the top of every file.
- One resource per file; the resource constructor takes only the `WayaPay` client.
- Validate at the boundary with `WayaPay::requireFields` (type `validation`); it throws before the request leaves your server.
- Throw `WayaPayException` with the right `type` so callers can branch.
- No comments explaining _what_ the code does — only add one when the _why_ is non-obvious.
