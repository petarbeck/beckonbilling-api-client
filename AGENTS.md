# AGENTS.md - Beckon Billing API client

Context for an AI coding agent working in a **consumer** project that uses the
`beckonbilling/api-client` PHP package. It describes everything needed to use
the library correctly without reading its source. (The canonical HTTP contract
is [`openapi.yaml`](openapi.yaml); this file is the client-usage companion.)

## What this package is

A thin, typed PHP wrapper over the Beckon Billing public REST API (`/api/v1`).
Namespace `BeckonBilling\ApiClient\`, PHP `>=8.2`, PSR-4, PSR-18/PSR-17 for
transport. It exposes eight entities - six writable (customers, article
categories, articles, quotes, outbound invoices, recurring invoices) and two
read-only (units, document templates) - plus user-token auth. It holds no secrets; a
valid API token is required to do anything.

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

### Common CRUD (the six writable entity resources)

```php
$res->list(array $query = [], array $options = []): Collection   // paginated page
$res->autoPaging(array $query = [], array $options = []): Generator // lazy over all pages
$res->get(string $id, array $options = []): Model
$res->create(array $data, array $options = []): Model              // 201
$res->update(string $id, array $data, array $options = []): Model  // partial (PUT)
$res->delete(string $id, array $options = []): void                // soft-delete
```

`units` and `documentTemplates` are **read-only**: `list()`, `autoPaging()` and
`get()` only. `create`/`update`/`delete` throw a `\LogicException` locally
rather than making a request the API answers 405.

Resource properties and their models:

| Property | Path | Model | Feature |
|---|---|---|---|
| `$client->customers` | `customers` | `Model\Customer` | `customers` |
| `$client->articleCategories` | `article-categories` | `Model\ArticleCategory` | `articles` |
| `$client->articles` | `articles` | `Model\Article` | `articles` |
| `$client->units` | `units` | `Model\Unit` | any of `articles`/`quotes`/`outbound_invoices`/`recurring_invoices` |
| `$client->documentTemplates` | `document-templates` | `Model\DocumentTemplate` | `quotes` or `outbound_invoices` |
| `$client->quotes` | `quotes` | `Model\Quote` | `quotes` |
| `$client->outboundInvoices` | `outbound-invoices` | `Model\OutboundInvoice` | `outbound_invoices` |
| `$client->recurringInvoices` | `recurring-invoices` | `Model\RecurringInvoice` | `recurring_invoices` |

### Quote actions (`$client->quotes`)

```php
->issue(string $id, array $options = []): Quote            // assign number
->send(string $id, array $data = [], array $options = []): Quote   // email PDF; needs `send`; $data may hold 'document_ids'
->sendResult(string $id, array $data = [], array $options = []): array // ['sent_to','quote','detached_positions']
->convert(string $id, ?string $scope = null, array $options = []): array // ['outbound_invoice_id'=>?string,'quote'=>?Quote]; needs outbound_invoices Full
->pdf(string $id, array $options = []): string             // raw PDF bytes (409 for drafts)
```

**A quote can be addressed to something other than a customer.** Pass
`recipient_kind` (`customer` | `lead` | `supplier` | `partner`) together with
`recipient_ref_id`; the printed recipient block is snapshotted server-side from
that record and `customer_id` is cleared. `lead` is QUOTE-ONLY - an outbound
invoice accepts only `customer`, `supplier` and `partner`, because an invoice
always names a customer.

Three rules worth knowing before you build on it:

- A kind the document does not accept is **refused** with 422
  `recipient_kind_invalid`. It is never silently ignored - doing so produced a
  draft with no recipient at all, which is exactly the failure this refusal
  exists to prevent.
- Unknown and foreign `recipient_ref_id` answer identically with 404
  `recipient_not_found`. Two different answers would be an existence oracle.
- `POST /quotes/{id}/convert` on a LEAD-addressed quote is refused with 409
  `quote_recipient_is_lead` unless that lead has already been completed into a
  customer (`POST /leads/{id}/complete`). The conversion then binds the invoice
  to that customer and keeps the quote's recipient snapshot.

`send` is the one quote action whose HTTP body is an ENVELOPE (`{sent_to,
quote}`) rather than a bare quote; `send()` unwraps it for you and
`sendResult()` hands you the whole thing. A quote with no recipient address and
no customer to fall back on is 422 `recipient_email_missing`; a mail failure is
502 `mail_send_failed` - unlike an invoice issue, which sends best-effort.

### Outbound-invoice actions (`$client->outboundInvoices`)

```php
->issue(string $id, array $data = [], array $options = []): OutboundInvoice  // issue + auto-send; needs `send`
->send(string $id, array $data = [], array $options = []): OutboundInvoice   // (re-)send; needs `send`
->cancel(string $id, array $options = []): OutboundInvoice                   // returns the CREDIT NOTE it created
->setPaid(string $id, bool $paid, array $options = []): OutboundInvoice      // needs `bank`; false 409s if tx-linked payments exist
->pdf(string $id, array $options = []): string                              // raw PDF bytes (409 for drafts)
```

**`issue()` has two side effects that never fail the call, so check the result,
not the status code:**

```php
$issued = $client->outboundInvoices->issue($id);
if ($issued->send_error)         { /* the customer was NOT emailed */ }
if ($issued->payment_link_error) { /* it offers online payment with no link behind it */ }
```

Both keys are present only when that thing went wrong. `payment_link_error` is
the one people miss: the invoice goes out telling the customer to pay online,
and the button leads nowhere.

`cancel()` answers the **credit note that was created**, not the invoice that
was cancelled - read `cancels_outbound_invoice_id` on it to name the other half.
Cancelling an invoice that never received money settles that credit note
automatically; one that was paid even in part leaves it open, because that is a
real refund owed to the customer.

Paid state is read back as derived, never-writable fields: `settlement_status`,
`paid` (bool), `paid_amount`, `remaining_amount`, and `paid_at`. `paid_at` is
null until the invoice is **fully** settled, then holds the date of the payment
that settled it (the payment's own date, so a back-dated payment records a
truthful settlement date - not the recording time); it is cleared again if the
invoice drops back to partially paid.

**To decide whether a document still owes money, read `settlement_status`**
(`draft` | `open` | `partially_paid` | `paid` | `cancelled`) or
`remaining_amount` - never `paid` on its own, and never a gross-minus-paid
subtraction of your own. A **cancelled** invoice is not a receivable: it was
reversed by a credit note, so it reports `remaining_amount` 0 even though `paid`
is false and no money ever arrived. Reading `paid: false` as "outstanding" makes
a storno look like an unpaid bill - the single most common way to misreport this
API, and the reason `settlement_status` exists.

A cancellation is a **pair**, and both halves name each other, so you never need
a second request to relate them: the credit note carries
`cancels_outbound_invoice_id` / `_number`, and the cancelled invoice carries
`cancelled_by_outbound_invoice_id` / `_number`. Whatever is still owed in either
direction lives on the credit note, never on the invoice it reversed - on a
credit note, `open` means *you* owe the customer a refund.

### Article variants (`$client->articles`)

A variant is a named set of OVERRIDES on a catalog article - "Premium",
"Klein", "Kleinunternehmer". It is a sub-collection, not an entity of its own,
because it has no meaning away from its article.

```php
->variants(string $articleId, array $query = [], array $options = []): Collection<ArticleVariant>
->createVariant(string $articleId, array $data, array $options = []): ArticleVariant
->updateVariant(string $articleId, string $variantId, array $data, array $options = []): ArticleVariant
```

**Every override field is genuinely nullable, and the three states are
different**: absent = leave as it is, `null` = clear the override and inherit
the article's value again, a value = a real override. `0` is a value - a price
of 0 or a tax rate of 0 is an override to zero, not "unset". So read them as
`$variant->price ?? $article->price`, never `$variant->price ?: $article->price`
- the second turns a 0 % Kleinunternehmer variant into the article's 20 %.

`label` is required and is never inherited. There is no delete: a variant a
document already used is retired in the portal, and the document keeps the
values and the label it snapshotted either way. `Article.variant_count` tells
you whether an article has any without a second request.

A document line names one with `article_variant_id`; the resolved values plus
`variant_label` are then snapshotted onto the line, so deleting or renaming the
variant later never changes what a document says was sold.

### Units (`$client->units`) - read-only

The organisation's unit vocabulary, and **the list the server validates a
position's `unit` against**: anything outside it is refused with 422
the vocabulary. So this is not a display nicety - read it before writing
positions and pick a `short` from it.

```php
foreach ($client->units->autoPaging() as $unit) {
    echo $unit->short, ' ', $unit->plural, "\n";   // "Monat" "Monate"
}
```

Why it is a vocabulary rather than free text: a document prints the unit
INFLECTED once the quantity leaves 1 ("12 Monate", not "12 Monat"), and only a
vocabulary entry carries a plural to inflect to. Empty `plural` means the unit
does not inflect ("8 h").

Two things that bite:

- The check runs on the **resolved** unit. Omitting `unit` does not skip it - it
  falls back to the named article's unit and then to the organisation's default,
  and it is *that* value which must be known.
- A unit **already stored** on the record you are saving always keeps passing,
  even after it is retired from the vocabulary. Otherwise deleting a unit would
  make every document that ever used it unsaveable, for edits that have nothing
  to do with it.

Readable by any token with View on any one of `articles`, `quotes`,
`outbound_invoices` or `recurring_invoices` - a token allowed to write a
document must be able to read what its documents are checked against.

### Document templates (`$client->documentTemplates`) - read-only

Document templates ("Vorlagen"), and where the ids for the write-only
`document_template_id` input come from:

```php
$templates = $client->documentTemplates->list(['kind' => 'invoice']);
$client->outboundInvoices->create([
    'customer_id'          => $customerId,
    'document_template_id' => $templates->data[0]->id,  // loads BOTH texts AND its days
    'positions'            => [ /* ... */ ],
]);
```

**This replaced `documentTerms` / `/document-terms`, which was removed** - the
old path now 404s, and the old input key `document_term_id` answers 422
`document_term_id_retired`. A template is the bigger thing a terms preset was a
part of: besides the wording and the days it carries the SECOND terms text
(`payment_terms_text`), the printed footer, the cover-mail text, the attachments
a new document starts with, and - per kind - the quote's default down payment or
the invoice's bank details. The organisation's DEFAULT template is now its
document setting, which is why those fields left the organisation.

Two things to get right:

- **The kind is `invoice`, not `outbound_invoice`.** The kinds differ from the
  removed document terms, so a value copied out of older code is simply unknown.
  A template of the wrong kind is refused with 404
  `document_template_not_found` - the same answer as an unknown or foreign id.
- **`days` is the payment term (or validity) the template applies**, and `0` is
  a real value meaning due immediately, so never fold it into a fallback. With
  no template at all the fallback is 30 days for a quote, 14 for an invoice.

Rows are filtered to the kinds the token may view: a quotes-only token never
sees the invoice templates, and asking for a kind it cannot view answers an
EMPTY LIST rather than 403 (which would leak that the other kind exists).

### Strict mode (`?strict=1`)

Pass it as a query option on any write and an unrecognised body key answers
**400 `unrecognised_keys`** naming the offenders, instead of being silently
discarded:

```php
$client->customers->create($data, ['query' => ['strict' => 1]]);
```

Opt-in, so nothing existing breaks - and worth turning on in development,
because the default behaviour cannot tell "saved" from "dropped". Two caveats:
it checks the request's shape only and runs before authentication (so it works
on `auth->login()` too), and it **silently does nothing on `/articles` and
`/article-categories`**, which declare no key list. Do not read a pass on those
two as a validated request.

### List filters

- `outboundInvoices->list()` filters: `status` (draft|issued), `paid` (0|1),
  `sent` (0|1), `cancelled` (0|1), `customer_id`, `q` (text). Booleans passed as
  PHP `true`/`false` are normalised to `1`/`0`.
- All lists accept `limit` (default 100, max 500) and `offset`.

## Models

Every read/write returns a model wrapping the JSON payload:

```php
$customer->display_name;  // magic property (documented fields have @property-read hints)
$customer['display_name'];// ArrayAccess
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
| `ValidationException` | 400/422 | rejected payload (`unit_too_long`, `unrecognised_keys`, `article_not_found`) |
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
$newInvoiceId = $result['outbound_invoice_id'];

// Recurring template (generated automatically by the portal's agent)
$client->recurringInvoices->create([
    'label' => 'Hosting', 'customer_id' => $customerId,
    'interval' => 'monthly', 'first_run_date' => '2026-08-01',
    'positions' => [['title' => 'Hosting', 'quantity' => 1, 'price' => 20, 'tax_percent' => 20]],
]);
```

## Field conventions (from the API)

- IDs are opaque UUID strings. The ONE exception is
  `RecurringInvoice.document_ids`, which carries internal integer ids and is not
  settable through this contract - do not build on it.
- Dates: calendar-day fields (`issue_date`, `due_date`, `valid_until`,
  `first_run_date`) are `YYYY-MM-DD`; timestamps are ISO offset datetimes; unset
  = `null`.
- **`due_days` on an outbound invoice is the payment term**, in calendar days
  from the issue date, and it is the key that MOVES `due_date` - `due_date`
  itself is read-only and has never been an input. It reads back derived
  (`due_date - issue_date`, null while either is unset). `0` is a real value
  meaning due immediately. **It was accepted and silently ignored until portal
  v1.4.x** - and worse, its mere presence disabled the preset branch too, so a
  request carrying it moved nothing at all. If you have been sending it as a
  no-op, it now takes effect.
  An explicit `due_days` beats the day count of a `document_template_id` sent in
  the same request.
- `RecurringInvoice.last_run_error` comes with **`last_run_severity`**
  (`''` | `warning` | `error`). Read it before calling a run failed: some
  messages describe a run that DID generate, export and send the invoice.
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
- **A quote can be billed in STAGES**, and the money only adds up if you know
  how. `convert()` takes a `$scope`: `null`/`'full'` bills the whole one-time
  part as it always did, `'deposit'` bills the quote's down payment, `'final'`
  bills the remainder. An unknown value is 422 `invoice_scope_unknown` - refused,
  not coerced, because falling back to `full` would silently bill everything.
  - Each invoice reports which part it is in **`invoice_scope`**
    (`full` | `deposit` | `final`). Everything not created as a staged invoice
    says `full`, including every invoice older than the field.
  - **Do not add a deposit and a final invoice together as if each were the
    whole amount.** They already sum: the final invoice lists the full one-time
    lines and then carries a NEGATIVE deduction line per tax rate for what the
    down payment covered, so its own `gross_total` is the remainder. Those
    negative lines are correct - do not filter them out.
  - **`OutboundInvoice.quote_id`** is the full trail back to the quote. Use it,
    not `Quote.converted_outbound_invoice_id`, which holds a single id and so
    cannot describe a quote billed as two invoices.
- `structured_totals` is **gone**; sending it does nothing.
- **`document_term_id` was RETIRED**: sending it - with any value at all -
  answers 422 `document_term_id_retired`. Its successor is
  **`document_template_id`**, which is likewise write-only: it loads that
  template's texts and days onto the document and is not returned, because
  nothing records which template was used. Read `terms_text` and
  `payment_terms_text` - those are what render. Being refused rather than
  ignored is deliberate: ignoring it would leave the document on the default
  template and silently move `valid_until` / `due_date`.
- A **customer** carries defaults per document kind - six fields each for
  quotes and invoices: `quote_template_id`, `quote_valid_days`,
  `quote_terms_override` / `quote_terms_text`,
  `quote_payment_terms_override` / `quote_payment_terms_text`, and the
  `invoice_*` twins (with `invoice_due_days`). `quote_document_term_id` and
  `invoice_document_term_id` were removed; the `*_template_id` pair replaced
  them, and an id that is unknown, foreign or of the wrong kind answers 404
  `document_template_not_found`.

  Assigning that customer to a quote or invoice applies them - the texts AND the
  day count, so `valid_until` / `due_date` move too. Full precedence, highest
  first: what your request explicitly sent, then the customer's own texts and
  days **where the matching switch is on**, then the customer's template, then
  the organisation's default template, then a bare fallback of 30 days for a
  quote and 14 for an invoice. A default, never a snapshot: the document takes a
  copy, so editing a customer cannot rewrite a document that already exists.
  - **The `*_override` switches are real booleans, not "the text is
    non-empty".** Switch on with an empty text is the meaningful combination
    "print nothing for this customer"; switch off means "use the template". A
    client that infers the switch from the text cannot express the first.
  - `*_valid_days` / `*_due_days`: **null means inherit, not 0**, since 0 is a
    real value.
- Positions may carry a per-line discount: `discount_type`
  (`none`|`percent`|`amount`) + `discount_value`.
- **`supply_type`** on a position (`service` | `goods`, empty behaves as
  `service`) decides which sentence a cross-border document prints: within the
  EU a service line carries the reverse-charge note and a goods line the
  intra-community-supply note; outside it, "not taxable at the recipient's
  place" against an export delivery. A mixed document prints both.
- A position may name a catalog **variant** with `article_variant_id`; the
  server then takes that variant's resolved values and snapshots its name into
  `variant_label`. Send the id and leave the label empty.
- Positions carry `unit` (short form, e.g. `h`, `Stk.`) and `unit_plural` (the
  form printed once the quantity leaves 1, e.g. `Monate`). Send `unit` and leave
  `unit_plural` empty - the server resolves it from the organisation's unit
  vocabulary and snapshots it, so renaming a unit never re-inflects an issued
  document. An empty plural means the unit does not inflect (`8 h`).
- **Every document carries TWO terms texts: `terms_text` and
  `payment_terms_text`**, both snapshotted from the selected or default document
  template at creation, and both printed - `terms_text` first. Editing or
  deleting a template never changes a document that already exists. One pair of
  names on quotes, outbound invoices and recurring invoices; only the label the
  portal shows differs (quote: Angebotsbedingungen / **Anzahlungs**bedingungen;
  invoice and recurring: Rechnungsbedingungen / **Zahlungs**bedingungen).
  - An invoice's `terms_text` is empty by default now - the "payable by
    {due_date}" sentence lives in `payment_terms_text`. **If you render only
    `terms_text` you will print nothing on a typical invoice.**
  - On a QUOTE `payment_terms_text` holds the down-payment terms and is printed
    **only when the quote carries a real down payment** - `deposit_type` set AND
    `deposit_value` above zero. At `percent` / `0` no deposit line appears in the
    summary either, so the paragraph would describe something invisible.
  - Converting a quote does NOT carry `payment_terms_text` onto the invoice: it
    means deposit terms on the one and payment terms on the other.
  - On a QUOTE, omit `valid_until` on create to let the template supply the
    window.
  - On an INVOICE there is no `due_date` input at all - it is read-only. The key
    that moves the date is **`due_days`** (see below).
  - `document_template_id` is write-only: it LOADS a template's texts and days.
    Browse them with `$client->documentTemplates->list(['kind' => 'quote'])`.
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
- Recurring invoices have **no generate endpoint**. The portal's automation
  agent creates invoices from them, once a day at 08:00 in the ORGANISATION's
  own timezone - so a poller watching `next_run_at` sees a per-organisation
  local schedule, not one fixed UTC hour.
- Only these eight entities are on `/api/v1` (plus article variants, as a
  sub-collection of an article); suppliers, projects, inbound invoices,
  banking, etc. are portal-internal and not reachable with a token.
- **Creating answers 201**, not 200. Everything else answers 200.
- A `unit` the organisation does not stock is ADDED to its vocabulary, and what
  comes back is the vocabulary's spelling (`stk.` reads back as `Stk.`) - pick from
  `$client->units`. See the Units section for the two ways this surprises you.
- `quotes->send()` answers an envelope on the wire, not a bare quote (the client
  unwraps it); `outboundInvoices->cancel()` answers the CREDIT NOTE, not the
  invoice you cancelled.
- An unrecognised body key is **silently discarded** unless you pass
  `?strict=1` - which itself does nothing on `/articles` and
  `/article-categories`.
