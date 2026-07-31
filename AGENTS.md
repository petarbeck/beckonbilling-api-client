# AGENTS.md - Beckon Billing API client

Context for an AI coding agent working in a **consumer** project that uses the
`beckonbilling/api-client` PHP package. It describes everything needed to use
the library correctly without reading its source. (The canonical HTTP contract
is [`openapi.yaml`](openapi.yaml); this file is the client-usage companion.)

## What this package is

A thin, typed PHP wrapper over the Beckon Billing public REST API (`/api/v1`).
Namespace `BeckonBilling\ApiClient\`, PHP `>=8.2`, PSR-4, PSR-18/PSR-17 for
transport. It exposes six entities (customers, article categories, articles,
quotes, outbound invoices, recurring invoices) plus user-token auth. It holds
no secrets; a valid API token is required to do anything.

## Install

```bash
composer require beckonbilling/api-client
# plus a PSR-18 client if the project has none:
composer require guzzlehttp/guzzle
```

## Construct the client

```php
use BeckonBilling\ApiClient\Client;

$client = new Client([
    'token'           => 'bbp_...',   // required for entity calls; optional for auth->login/register
    'base_uri'        => 'https://portal.beckonbilling.com', // portal host; '/api/v1' is appended automatically
    'organisation'    => 'org-uuid',  // optional default ?organisation= scope
    'user_agent'      => '...',        // optional
    'http_client'     => $psr18,       // optional; auto-discovered (php-http/discovery) otherwise
    'request_factory' => $psr17,       // optional; auto-discovered otherwise
    'stream_factory'  => $psr17,       // optional; auto-discovered otherwise
]);
```

- `base_uri` accepts the host root **or** a URL already ending in `/api/v1` (not
  doubled).
- Invalid config throws `\InvalidArgumentException`; a missing PSR-18 client
  throws `\RuntimeException` with install guidance.

## Tokens & auth

Two token flavours, both sent as `Authorization: Bearer <token>`:

- **Organisation token** (`bbp_...`): bound to one organisation, carries a
  feature grant map + `send`/`bank` capabilities. Just pass it as `token`.
- **User token**: minted via `auth->login()`; acts as the signed-in user across
  their organisations. Select one with `organisation`.

```php
// no token yet:
$client  = new Client(['base_uri' => 'https://portal.beckonbilling.com']);
$session = $client->auth->login('user@example.com', 'secret', [
    'tfa_code'        => '123456',  // required only if 2FA is enabled (see below)
    'remember_device' => true,       // returns a 30-day 'device_token'
]);
// $session = ['token','expires_at','device_token'?,'user','organisations']

$client->auth->register(['email' => 'x@y.z', 'name' => 'X']); // pending account + activation email
$client->auth->me();                                          // current user + organisations (needs a user token)
```

- 2FA: `login()` throws `AuthenticationException` with `getErrorKey() ===
  'tfa_required'` until a `tfa_code` (TOTP, recovery, or emailed code) is passed.
- `auth->*` never sends an `organisation` param.

## Organisation scoping

- Config `organisation` applies to every call.
- Per-call override: pass `['organisation' => 'other-uuid']` as the last
  argument; pass `['organisation' => null]` to omit the param for that call.
- A user token with exactly one organisation may omit it. Missing with several
  organisations -> 400 `organisation_is_required`; wrong/foreign -> 403.

## Resources & method signatures

Access via properties on `$client`. `$options` (last arg everywhere) is an
assoc array; its common key is `organisation`.

### Common CRUD (all six entity resources)

```php
$res->list(array $query = [], array $options = []): Collection   // paginated page
$res->autoPaging(array $query = [], array $options = []): Generator // lazy over all pages
$res->get(string $id, array $options = []): Model
$res->create(array $data, array $options = []): Model
$res->update(string $id, array $data, array $options = []): Model  // partial (PUT)
$res->delete(string $id, array $options = []): void                // soft-delete
```

Resource properties and their models:

| Property | Path | Model | Feature |
|---|---|---|---|
| `$client->customers` | `customers` | `Model\Customer` | `customers` |
| `$client->articleCategories` | `article-categories` | `Model\ArticleCategory` | `articles` |
| `$client->articles` | `articles` | `Model\Article` | `articles` |
| `$client->quotes` | `quotes` | `Model\Quote` | `quotes` |
| `$client->outboundInvoices` | `outbound-invoices` | `Model\OutboundInvoice` | `outbound_invoices` |
| `$client->recurringInvoices` | `recurring-invoices` | `Model\RecurringInvoice` | `recurring_invoices` |

### Quote actions (`$client->quotes`)

```php
->issue(string $id, array $options = []): Quote            // assign number
->send(string $id, array $data = [], array $options = []): Quote   // email PDF; needs `send`; $data may hold 'document_ids'
->convert(string $id, array $options = []): array          // ['invoice_id'=>?string,'quote'=>?Quote]; needs outbound_invoices Full
->pdf(string $id, array $options = []): string             // raw PDF bytes (409 for drafts)
```

### Outbound-invoice actions (`$client->outboundInvoices`)

```php
->issue(string $id, array $data = [], array $options = []): OutboundInvoice  // issue + auto-send; needs `send`; check ->send_error
->send(string $id, array $data = [], array $options = []): OutboundInvoice   // (re-)send; needs `send`
->cancel(string $id, array $options = []): OutboundInvoice                   // creates the credit note
->setPaid(string $id, bool $paid, array $options = []): OutboundInvoice      // needs `bank`; false 409s if tx-linked payments exist
->pdf(string $id, array $options = []): string                              // raw PDF bytes (409 for drafts)
```

Paid state is read back as four derived, never-writable fields: `paid` (bool),
`paid_amount`, `remaining_amount`, and `paid_at`. `paid_at` is null until the
invoice is **fully** settled, then holds the date of the payment that settled it
(the payment's own date, so a back-dated payment records a truthful settlement
date - not the recording time); it is cleared again if the invoice drops back to
partially paid.

### List filters

- `outboundInvoices->list()` filters: `status` (draft|issued), `paid` (0|1),
  `sent` (0|1), `cancelled` (0|1), `customer_id`, `q` (text). Booleans passed as
  PHP `true`/`false` are normalised to `1`/`0`.
- All lists accept `limit` (default 100, max 500) and `offset`.

## Models

Every read/write returns a model wrapping the JSON payload:

```php
$customer->label;         // magic property (documented fields have @property-read hints)
$customer['label'];       // ArrayAccess
$customer->id();          // string|null UUID
$customer->get('x', $d);  // with default
$customer->has('x');      // bool
$customer->toArray();     // full payload array
json_encode($customer);   // serialises to the payload
$customer->new_api_field; // fields added by the API later are still reachable
```

Models are **immutable** (writing a key throws `\LogicException`). Typed helpers:
`Quote::isDraft()`, `OutboundInvoice::isPaid()/isDraft()/isCreditNote()`.

## Pagination

`list()` returns `BeckonBilling\ApiClient\Collection`:

```php
$page->data;         // list<Model>
$page->total;        // int, full count
$page->limit;        // int
$page->offset;       // int
$page->hasMore();    // bool
$page->nextOffset(); // ?int
count($page);        // Countable
foreach ($page as $model) { ... } // IteratorAggregate
```

`autoPaging(['limit' => 100])` yields every item across pages lazily.

## Errors

Non-2xx throws a subclass of `BeckonBilling\ApiClient\Exception\ApiException`:

| Class | Status | Notes |
|---|---|---|
| `AuthenticationException` | 401 | bad/expired token; `tfa_required` on login |
| `PermissionException` | 403 | `missing_permission`, `send_not_permitted`, `bank_not_permitted` |
| `NotFoundException` | 404 | absent or foreign-organisation |
| `ConflictException` | 409 | wrong state (draft PDF, delete issued, un-pay linked) |
| `ValidationException` | 400/422 | rejected payload |
| `RateLimitException` | 429 | back off |
| `ServerException` | 5xx | retryable |
| `TransportException` | 0 | network failure; original PSR-18 error is `->getPrevious()` |

Each exposes `getStatusCode(): int`, `getErrorKey(): ?string`,
`getApiErrorCode(): ?int`, `getResponse(): ?ResponseInterface`. **Branch on
`getErrorKey()`**, not the message.

```php
use BeckonBilling\ApiClient\Exception\ApiException;
try {
    $client->outboundInvoices->setPaid($id, true);
} catch (ApiException $e) {
    match ($e->getErrorKey()) {
        'bank_not_permitted' => /* token lacks the bank capability */ null,
        default              => throw $e,
    };
}
```

## Capabilities (send / bank)

Independent of the feature grant, two capabilities gate side effects (default
OFF for non-owners):

- `send` - `quotes->send`, `outboundInvoices->issue`, `outboundInvoices->send`.
- `bank` - `outboundInvoices->setPaid`.

Lacking one -> `PermissionException` with key `send_not_permitted` /
`bank_not_permitted`. There is no way to grant these from the API; the
organisation owner sets them in the portal per token.

## End-to-end examples

```php
// Draft -> issue -> pay an invoice
$invoice = $client->outboundInvoices->create([
    'customer_id' => $customerId,
    'positions'   => [
        ['title' => 'Consulting', 'quantity' => 1, 'price' => 1000, 'tax_percent' => 20],
    ],
]);
$issued = $client->outboundInvoices->issue($invoice->id()); // needs `send`
if ($issued->send_error) { /* mailing failed, invoice still issued */ }
$client->outboundInvoices->setPaid($issued->id(), true);     // needs `bank`

// Quote -> convert to invoice
$quote  = $client->quotes->create(['customer_id' => $customerId, 'positions' => [/* ... */]]);
$client->quotes->issue($quote->id());
$result = $client->quotes->convert($quote->id());            // needs outbound_invoices Full
$newInvoiceId = $result['invoice_id'];

// Recurring template (generated automatically by the daily agent)
$client->recurringInvoices->create([
    'label' => 'Hosting', 'customer_id' => $customerId,
    'interval' => 'monthly', 'first_run_date' => '2026-08-01',
    'positions' => [['title' => 'Hosting', 'quantity' => 1, 'price' => 20, 'tax_percent' => 20]],
]);
```

## Field conventions (from the API)

- IDs are opaque UUID strings.
- Dates: calendar-day fields (`issue_date`, `due_date`, `valid_until`,
  `first_run_date`) are `YYYY-MM-DD`; timestamps are ISO offset datetimes; unset
  = `null`.
- Amounts are decimal net numbers; `tax_percent` is a percent (e.g. `20`).
- **Never re-derive line money.** Each position carries read-only `line_net`,
  `line_tax` and `line_gross`. `quantity * price` is wrong for any line with a
  discount, and the tax applies to the discounted net.
- **`billing_mode`** (`one_time` | `recurring`, plus `recurring_interval`) marks
  a line as a repeating fee. On a quote this splits the printed summary per
  modality instead of adding a one-off charge to a monthly one. Converting such a
  quote produces an invoice for the one-time lines AND a recurring invoice per
  interval - but `POST /quotes/{id}/convert` creates only the invoice, so an
  all-recurring quote is refused with 409 `quote_is_recurring_only`.
- `POST /quotes/{id}/convert` answers **201** with **`outbound_invoice_id`**.
- `structured_totals` is **gone**; sending it does nothing.
- A **customer** carries default Bedingungen per document kind
  (`quote_document_term_id` / `quote_terms_text`,
  `invoice_document_term_id` / `invoice_terms_text`). Assigning that customer to
  a quote or invoice applies them - text AND the day count, so `valid_until` /
  `due_date` move with the terms. Precedence: what your request explicitly sent,
  then the customer's default, then the organisation's. A default, never a
  snapshot: the document takes a copy, so editing a customer cannot rewrite a
  document that already exists.
- Positions may carry a per-line discount: `discount_type`
  (`none`|`percent`|`amount`) + `discount_value`.
- Positions carry `unit` (short form, e.g. `h`, `Stk.`) and `unit_plural` (the
  form printed once the quantity leaves 1, e.g. `Monate`). Send `unit` and leave
  `unit_plural` empty - the server resolves it from the organisation's unit
  vocabulary and snapshots it, so renaming a unit never re-inflects an issued
  document. An empty plural means the unit does not inflect (`8 h`).
- Quotes and invoices carry `terms_text`: the effective payment/validity terms,
  snapshotted from the organisation's default terms preset at creation.
  `document_term_id` is provenance only. Editing or deleting a preset never
  changes an issued document. Omit `valid_until`/`due_date` on create to let the
  preset supply the window.
- Quotes support a down payment: `deposit_type`/`deposit_value` in, resolved
  `deposit_amount` + `remaining_amount` out.
- Quotes and invoices carry `small_business` (the issuer's Kleinunternehmer VAT
  exemption, snapshotted) and `vat_exemption_note`. When the flag is true the
  document must be rendered with a **single total plus that note, instead of a
  net/VAT breakdown** - showing it as an ordinary 0 % document is legally wrong.
  The note's citation differs per issuer country and follows the organisation's
  language; print it as given.
- Phone fields are E.164 (`+436601791301`).
- `public_index` (document number) is an opaque string.
- Recipient is a structured object; `customer_type`/`type` is `business` or
  `consumer`.

## Gotchas

- Downloading a **draft** PDF 409s - issue it first.
- **Deleting** an issued invoice 409s - `cancel()` it (creates a credit note).
- `setPaid($id, false)` 409s while transaction-linked payments exist.
- Recurring invoices have **no generate endpoint** - the portal's daily agent
  creates invoices from them.
- Only these six entities are on `/api/v1`; suppliers, projects, inbound
  invoices, banking, etc. are portal-internal and not reachable with a token.
