# Beckon Billing API client (PHP)

[![CI](https://github.com/petarbeck/beckonbilling-api-client/actions/workflows/ci.yml/badge.svg)](https://github.com/petarbeck/beckonbilling-api-client/actions/workflows/ci.yml)
[![Packagist](https://img.shields.io/packagist/v/beckonbilling/api-client.svg)](https://packagist.org/packages/beckonbilling/api-client)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

The official PHP client for the [Beckon Billing](https://www.beckonbilling.com)
REST API (v1) - a small, typed, idiomatic wrapper over the public
`/api/v1` surface: customers, article categories, articles, quotes, outbound
invoices and recurring invoices, the read-only units and document-templates
vocabularies, plus user-token authentication.

- **PSR-4 / PHP `>=8.2`**, no framework required.
- **Bring your own HTTP client** - any [PSR-18](https://www.php-fig.org/psr/psr-18/)
  client works and is auto-discovered.
- **Typed exceptions** you can branch on with a stable `error.key`.
- The canonical API contract lives in [`openapi.yaml`](openapi.yaml) (import it
  into Postman/Insomnia). Building AI tooling on top? See [`AGENTS.md`](AGENTS.md).

> This is a thin, open-source HTTP wrapper - it holds no secrets and no server
> code. You still need a valid API token to do anything.

## Installation

```bash
composer require beckonbilling/api-client
```

You also need any PSR-18 HTTP client + PSR-17 factories. If you don't already
have one, add Guzzle (auto-discovered):

```bash
composer require guzzlehttp/guzzle
```

## Quick start

```php
require 'vendor/autoload.php';

use BeckonBilling\ApiClient\Client;

$client = new Client([
    'token'        => 'bbp_your_token',                 // organisation or user token
    'base_uri'     => 'https://portal.beckonbilling.com', // your portal host
    'organisation' => 'organisation-uuid',              // optional default scope
]);

// List open invoices
$page = $client->outboundInvoices->list(['status' => 'issued', 'paid' => 0]);
foreach ($page as $invoice) {
    echo $invoice->public_index, ' - ', $invoice->gross_total, "\n";
}

// Create a customer
$customer = $client->customers->create([
    'label'         => 'Musterfirma AG',
    'customer_info' => "Musterfirma AG\nIndustriestraße 45\n1220 Wien",
]);

// Download an invoice PDF
file_put_contents('invoice.pdf', $client->outboundInvoices->pdf($invoice->id()));
```

## Authentication

Every request sends `Authorization: Bearer <token>`. There are two token
flavours - both authorise through the same per-feature model:

### Organisation tokens

Created in the portal under **Firma -> API-Zugriff**. Bound to one organisation;
carry an explicit permission grant + the `send`/`bank` capabilities. Pass the
`bbp_...` token as `token`.

### User tokens (app sign-in)

Mint a token from email + password - no portal visit:

```php
$client = new Client(['base_uri' => 'https://portal.beckonbilling.com']); // no token yet

$session = $client->auth->login('user@example.com', 'secret');
// $session = ['token' => 'bbp_...', 'expires_at' => ..., 'user' => ..., 'organisations' => [...]]

$authed = new Client([
    'token'        => $session['token'],
    'base_uri'     => 'https://portal.beckonbilling.com',
    'organisation' => $session['organisations'][0]['id'],
]);
```

If two-factor authentication is enabled, `login()` throws an
`AuthenticationException` whose `getErrorKey()` is `tfa_required` until you pass
a code:

```php
$session = $client->auth->login('user@example.com', 'secret', [
    'tfa_code'        => '123456',
    'remember_device' => true, // returns a 30-day device_token
]);
```

## Organisation scoping

Set a default `organisation` in the config, or override per call:

```php
$client->customers->list([], ['organisation' => 'another-org-uuid']);
$client->customers->list([], ['organisation' => null]); // send no organisation param
```

A user token with exactly one organisation may omit it entirely.

## Resources

| Property | Entity | Feature |
|---|---|---|
| `$client->customers` | Customers | `customers` |
| `$client->articleCategories` | Article categories | `articles` |
| `$client->articles` | Articles | `articles` |
| `$client->units` | Units (read-only) | none - any valid token |
| `$client->documentTemplates` | Document templates (read-only) | `quotes` / `outbound_invoices` |
| `$client->quotes` | Quotes | `quotes` |
| `$client->outboundInvoices` | Outbound invoices | `outbound_invoices` |
| `$client->recurringInvoices` | Recurring invoices | `recurring_invoices` |
| `$client->auth` | User-token auth | - |

Each writable resource offers `list()`, `autoPaging()`, `get()`, `create()`,
`update()`, `delete()`. Quotes and invoices add lifecycle actions
(`issue`, `send`, `convert`/`cancel`, `setPaid`, `pdf`); articles add variants.
`units` and `documentTemplates` are read-only - `list()`, `autoPaging()`, `get()`.

## Pagination

Lists return a `Collection` (`data`, `total`, `limit`, `offset`; iterable and
countable):

```php
$page = $client->customers->list(['limit' => 50, 'offset' => 0]);
$page->total;      // full server-side count
$page->hasMore();  // more pages available?
```

Or iterate everything lazily:

```php
foreach ($client->customers->autoPaging() as $customer) {
    // ...
}
```

## Error handling

Non-2xx responses throw a typed exception. Every one exposes
`getStatusCode()`, `getErrorKey()` (the stable slug) and `getResponse()`.

```php
use BeckonBilling\ApiClient\Exception\ApiException;
use BeckonBilling\ApiClient\Exception\PermissionException;

try {
    $client->outboundInvoices->issue($id);
} catch (PermissionException $e) {
    if ($e->getErrorKey() === 'send_not_permitted') {
        // token lacks the `send` capability
    }
} catch (ApiException $e) {
    // any other API error
    error_log($e->getStatusCode() . ' ' . $e->getErrorKey() . ': ' . $e->getMessage());
}
```

| Exception | Status |
|---|---|
| `AuthenticationException` | 401 |
| `PermissionException` | 403 (incl. `send_not_permitted` / `bank_not_permitted`) |
| `NotFoundException` | 404 |
| `ConflictException` | 409 |
| `ValidationException` | 400 / 422 |
| `RateLimitException` | 429 |
| `ServerException` | 5xx |
| `TransportException` | network failure (no response) |

All extend `ApiException`, so a single `catch (ApiException $e)` covers everything.

## Custom HTTP client

Inject any PSR-18 client / PSR-17 factories (for a preconfigured Guzzle,
retries, proxies, timeouts, test doubles ...):

```php
use GuzzleHttp\Client as Guzzle;
use Nyholm\Psr7\Factory\Psr17Factory;

$psr17 = new Psr17Factory();
$client = new Client([
    'token'           => 'bbp_...',
    'base_uri'        => 'https://portal.beckonbilling.com',
    'http_client'     => new Guzzle(['timeout' => 10]),
    'request_factory' => $psr17,
    'stream_factory'  => $psr17,
]);
```

## Working models

Resource objects wrap the JSON payload: read documented fields as properties or
array keys, and any field the API adds later stays reachable without a client
upgrade.

```php
$customer->label;            // property access
$customer['label'];          // array access
$customer->id();             // the UUID
$customer->toArray();        // the full payload
$invoice->isPaid();          // a few typed helpers on quotes/invoices
```

## Contributing / development

```bash
composer install
composer test        # PHPUnit, fully mocked - no network
```

The API contract in `openapi.yaml` and the agent guide in `AGENTS.md` are part
of the release and are kept in sync with the API surface.

## License

MIT - see [LICENSE](LICENSE).
