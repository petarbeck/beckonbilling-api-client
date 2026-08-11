# Changelog

All notable changes to this project are documented here. This project adheres
to [Semantic Versioning](https://semver.org/).

## [0.11.0] - 2026-08-11

### Added

- **A quote can be addressed to a LEAD, a supplier or a partner** - not only a
  customer. `Quote` and `QuoteInput` gain `recipient_kind`
  (`customer` | `lead` | `supplier` | `partner`) and `recipient_ref_id`. Pass
  both; the recipient block is snapshotted server-side from that record and
  `customer_id` is cleared. `lead` is quote-only: an invoice always names a
  customer.
- **`POST /quotes/{id}/convert` can now answer 409 `quote_recipient_is_lead`.**
  A lead-addressed quote converts only once that lead has been completed into a
  customer (`POST /leads/{id}/complete`); the invoice is then bound to that
  customer and the quote's recipient snapshot is preserved. If you convert
  quotes programmatically, handle this alongside the existing refusals.

### Fixed (documentation only - no server change)

- **`OutboundInvoice` now documents `recipient_kind` / `recipient_ref_id`.** The
  server has returned them since invoices learned supplier and partner
  recipients; only the output schema was missing them, so a generated client
  dropped two fields that were on the wire.
- **`openapi.yaml` parses under a strict YAML parser again.**
  `RecurringInvoiceInput` carried `payment_mode` and `payment_methods` twice.
  The two copies were byte-identical, so nothing was ambiguous in practice - but
  a duplicated mapping key is a hard error for strict parsers and for several
  code generators, which means the file could not be consumed by them at all.

## [0.10.0] - 2026-08-11

### Changed (BREAKING, and it retracts a claim 0.9.0 made an hour earlier)

- **An unknown `unit` is ADOPTED into the organisation's vocabulary, not
  refused.** 0.9.0 announced a 422 `unit_unknown` and told you your writes would
  start failing. That refusal never reached a deployed server: the portal
  reversed it before release, on the reasoning that it made a caller who knows a
  unit we happen not to stock unable to bill for it. **If you changed anything
  because of 0.9.0's note, you can change it back.**

  What actually happens now, and the part worth reading: a `unit` outside the
  vocabulary creates an entry (short form and label are the value you sent, the
  plural comes from a `unit_plural` sent alongside and is otherwise empty,
  meaning it does not inflect). Matching is trimmed and case-insensitive, and
  **the value stored is the vocabulary's spelling** - send `stk.` against a
  vocabulary holding `Stk.` and you read back `Stk.`, rather than creating a
  second entry for one unit. So the field is no longer something you can send
  and forget; read back what the server stored.

  `unit_unknown` no longer exists. The one refusal left is **422
  `unit_too_long`**, over 32 characters, because storing it would truncate
  silently.

  `GET /units` matters MORE under this rule, not less: it is now the way to
  avoid growing someone's vocabulary by accident, and the way to learn the
  spelling that will come back.

### Note

- 0.9.0's other headline, `due_days` becoming load-bearing, is unaffected and
  still applies exactly as described there.

## [0.9.0] - 2026-08-11

### Changed (BREAKING)

- **`due_days` on an outbound invoice now does something.** It has been an
  accepted input all along and was **read only to switch the terms branch off,
  then never applied**. Precisely: a request carrying `due_days` moved nothing -
  not the key itself, and not the day count of a `document_term_id` sent beside
  it, because the key's mere PRESENCE disabled that branch. A request WITHOUT
  `due_days` was unaffected, so a preset alone always worked. It applies from
  portal **v1.4.x**, and an explicit `due_days` beats a preset's days sent in
  the same request.

  (An organisation's or customer's default preset is seeded at draft creation,
  before this runs, and was never affected either way.)

  If you have been sending `due_days` as a harmless no-op, **your invoices'
  due dates will now move.** Check what you send before upgrading the portal.

  `due_days` also reads back now, DERIVED as `due_date - issue_date` (null while
  either is unset). `due_date` itself is **not** an input and never was -
  `AGENTS.md` said to "omit `due_date` on create to let the preset supply the
  window", which was never true of a key the API does not read.

- **A unit must come from the organisation's vocabulary.** A position's `unit`,
  and an article's, is refused with **422 `unit_unknown`** unless it matches one
  of `GET /units`. Free text used to be accepted, so anything posting a typed
  unit starts failing. The reason is the printed document: a unit is inflected
  once the quantity leaves 1 ("12 Monate"), and only a vocabulary entry carries
  the plural to inflect to.

  Two things that are easy to get wrong, and both are escape hatches rather
  than traps. A unit **already stored** on the record being saved always keeps
  passing, even once it is retired from the vocabulary, so deleting a unit never
  makes an existing document unsaveable. And a unit **inherited** rather than
  named - omitting it falls back to the catalog article's unit and then to the
  organisation's default - passes too, even when that inherited value is itself
  off-vocabulary. Both of those are guarded where they are SET, so an article
  grandfathered there stays sellable; only a value the caller genuinely invents
  is refused.

### Added

- **`GET /units` + `$client->units`** (read-only) - the organisation's unit
  vocabulary: `short`, `plural`, `label`, `display_label`, `position`. This is
  the list the server validates against, so it is what to pick from rather than
  a display nicety. Readable with View on any one of `articles`, `quotes`,
  `outbound_invoices` or `recurring_invoices`: a token allowed to write a
  document has to be able to read what its documents are checked against.
- **`GET /document-terms` + `$client->documentTerms`** (read-only) - terms
  presets: `kind`, `label`, `days`, `text`, `is_default`. Where the ids for the
  write-only `document_term_id` input come from. Filter with
  `list(['kind' => 'quote'])`. Rows are filtered to the kinds the token may
  view, and asking for a kind it may not answers an **empty list**, not 403,
  which would leak that the other kind exists. `days` of `0` is a real term
  ("due immediately"), never "unset".
- **`Resource\ReadOnlyResource`** - `create`/`update`/`delete` on those two
  throw `\LogicException` locally instead of spending a request on a 405.
- **`OutboundInvoice.payment_link_error`** - the second best-effort side effect
  of issuing, beside the already-documented `send_error`. Neither fails the
  issue, so **both have to be read off a 200 result**; this one means the
  invoice went out offering online payment with no Stripe link behind it.
- **`RecurringInvoice.last_run_severity`** (`''` | `warning` | `error`) - 0.8.0
  documented `last_run_at` and `last_run_error` and missed the third of the
  trio, which is the one saying whether a non-empty message is a failed run or a
  run that DID bill and needs a look afterwards.
- **`Quotes::sendResult()`** - the full send envelope (`sent_to`, `quote`,
  `detached_positions`).
- Input keys that were accepted all along and absent from the contract:
  `terms_text` on all three documents; `service_period_mode`, `reverse_charge`,
  `payment_mode`, `payment_methods`, `document_send_mode`, `email_text`,
  `email_body`, `pdf_footer`, `pdf_note`, `project_id` and `reference_fields` on
  `RecurringInvoiceInput`; `deposit_type`/`deposit_value`, `recipient`,
  `recipient_email`, `partner_id`, `project_id`, `document_ids`, `email_text`
  and `reference_fields` on `QuoteInput`; `due_days`, `recipient`, the SEPA
  payout trio (`recipient_account_holder`/`_iban`/`_bic`),
  `recipient_kind`/`recipient_ref_id`, `partner_id`, `project_id`,
  `document_ids`, `service_period`, `tax_label`, `email_text`, `pdf_note`,
  `payment_qr_code` and `reference_fields` on `OutboundInvoiceInput` - which
  documented 8 of roughly 30.
- `ArticleInput` gained `unit`, `supply_type`, `billing_mode`,
  `recurring_interval`; the `Article` OUTPUT gained the last three plus
  `group_label`. 0.8.0 crossed these wires - the model file learned
  `billing_mode`/`recurring_interval` while `openapi.yaml` learned
  `variant_count`, and each stayed silent about the other's.
- `Customer` and `CustomerInput` gained the address block (`address`, `zip`,
  `city`, `country`, `vat_id`, `additional`, `email`), the bank fields, the
  partner fields, `hourly_rate` and `document_send_mode` - core fields the
  contract had simply never described.
- `?strict=1` is documented for the first time, **including the fact that it
  silently does nothing on `/articles` and `/article-categories`**, which
  declare no key list. A validation flag that quietly validates nothing on two
  endpoints is worse unmentioned than mentioned.
- Documented that **creating answers 201** (six paths said 200), that
  `Position.unit` carries the `unit_unknown` rule, and that
  `RecurringInvoice.document_ids` is the one place this API exposes internal
  integer ids rather than UUIDs.

### Fixed

- **`Quotes::convert()` always returned a null invoice id.** It read
  `invoice_id`; the API answers **`outbound_invoice_id`**, as `AGENTS.md` itself
  has said since 0.6.x. The test mocked the wrong key too, so it passed while
  the method was broken against every real server. It now reads the real key and
  returns it under BOTH names, so nothing that read `invoice_id` breaks - it
  simply starts receiving a value.
- **`Quotes::send()` returned an empty Quote.** That endpoint answers an
  envelope (`{ sent_to, quote }`), not a bare quote, so hydrating the whole
  payload as the model left every field null with the real quote buried at
  `->quote`. It now unwraps it.
- **`Model\Customer` documented six properties the API has not emitted since the
  2026-07-03 structured-recipient migration**: `recipient`,
  `billing_email_address`, `billing_email_name`, `contact_name`,
  `contact_email`, `contact_phone`. `$customer->contact_email` returned null
  forever, silently. `openapi.yaml` was corrected in 0.6.3; the model file was
  missed and shipped rotten through 0.8.0. It now mirrors what the API emits.
- `Model\Quote` documented `footer_comment`, which is no longer emitted - the
  field is `pdf_footer`. Same class of rot, found by the same audit.
- `Model\Article`'s docblock had `tax_percent`'s description appended onto the
  `unit` line.
- Every remaining "daily agent" was corrected to **the automation agent, which
  runs once a day at 08:00 in the ORGANISATION's own timezone**. `DailyAgent` is
  decommissioned, and the schedule is now per-organisation local time - visible
  to anyone polling `next_run_at`, which no longer moves on one fixed UTC hour.
- `AGENTS.md`, `README.md` and `llms.txt` said "six entities"; there are eight.
- Documented what `cancel()` really answers (the **credit note** it created, not
  the invoice you cancelled) and the two 409s it can raise.

### Migration
```diff
-$id = $client->quotes->convert($quoteId)['invoice_id'];   // was always null
+$id = $client->quotes->convert($quoteId)['outbound_invoice_id'];

-$quote = $client->quotes->send($quoteId);                 // was an empty model
+$quote = $client->quotes->send($quoteId);                 // now the real quote

-'unit' => 'Stunden',                                      // now 422 unit_unknown
+'unit' => $client->units->list()->data[0]->short,
```

## [0.8.0] - 2026-08-10

### Added
- **Article variants** - `GET`/`POST /articles/{id}/variants` and
  `PUT /articles/{id}/variants/{variantId}`, with the `ArticleVariant` model and
  `$client->articles->variants()/createVariant()/updateVariant()`. A variant is
  a named set of OVERRIDES on a catalog article ("Premium", "Klein",
  "Kleinunternehmer"); a field it does not set is inherited, so a variant that
  overrides nothing produces exactly what the plain article produces. It is a
  sub-collection rather than an entity of its own because it has no meaning away
  from its article - and there is deliberately no DELETE.

  **Three states, and they are different:** absent = leave as it is, `null` =
  clear the override and inherit again, a value = a real override. `0` is a
  value. Read `$variant->price ?? $article->price`, never
  `$variant->price ?: $article->price`.
- **`Article.variant_count`** (read-only) - how many live variants an article
  has, so a catalog listing does not need a request per article.
- **`Position.article_variant_id` + `Position.variant_label`** - a document line
  may be priced from a variant. The resolved values and the variant's NAME are
  snapshotted onto the line, so deleting or renaming the variant later never
  changes what a document says was sold. Send the id and leave the label empty;
  the server resolves it.
- **`Position.supply_type`** (`service` | `goods`, empty behaves as `service`) -
  documented now, having been accepted and returned since the reverse-charge
  round. It decides which sentence a cross-border document prints: within the EU
  a service line carries the reverse-charge note and a goods line the
  intra-community-supply note; outside it, "not taxable at the recipient's
  place" against an export delivery.
- **`created_by` + `created_by_name`** on `Customer`, `Article`, `Quote`,
  `OutboundInvoice` and `RecurringInvoice`. `created_by` is the creating
  user's uuid, or `null` when the record has no recorded creator - a legacy
  row from before this field existed, or something a background process
  produced (a recurring run, an automated import). `created_by_name` mirrors
  it as a display string, `''` when `created_by` is null. Both are read-only;
  neither appears on any `*Input` schema. On `RecurringInvoice` the field
  describes who created the TEMPLATE - the invoices the daily agent generates
  from it are always system-created, regardless of who owns the template.
- **`RecurringInvoice.last_run_at` + `RecurringInvoice.last_run_error`**
  (read-only). A failed automated run used to exist only in the portal's own
  log, indistinguishable from "never ran" to anyone reading through this
  client. `last_run_at` is the last time the daily agent actually ATTEMPTED
  this template (generate + send) - never set on a run skipped because the
  period was already generated. `last_run_error` is that attempt's failure
  message, empty when it succeeded; it is CLEARED by the next successful run,
  so a non-empty value always means "still needs attention" right now. Poll
  it to detect a template the agent could not bill (e.g. the organisation has
  no SMTP configured) without watching the portal itself.

### Fixed
- **`ArticleVariant.supply_type` / `billing_mode` / `recurring_interval`** wrote
  their nullability twice, in a way that cancelled itself out: `type: string`
  beside an `enum` listing `null`. This document declares OpenAPI 3.1, where a
  `string` type excludes null, so a strict reader made exactly the branch that
  carries the meaning - `null` clears the override - unreachable. They now say
  it the way every other nullable field here does: `nullable: true`, with the
  enum naming only the real values. Documentation only; the API always accepted
  and returned null on these fields.

### Removed (BREAKING)
- **`Customer.label` is gone**, replaced by the read-only **`display_name`**.
  The label was a second name standing beside the company name, and the two were
  confused constantly - beta testers regularly maintained the wrong one of the
  pair. `display_name` is `company_name` for a business and `salutation` +
  `person_name` for a private person; sorting and searching follow it too.
- `CustomerInput.label` is gone. A `label` in the body is ignored, and rejected
  with 400 under `?strict=1`.

Only `Customer` is affected: supplier, partner and lead are not v1 entities, so
the identical rename on those does not reach this contract.

### Migration
```diff
-echo $customer->label;
+echo $customer->display_name;

-$client->customers()->create([ 'label' => 'ACME', ... ]);
+$client->customers()->create([ 'company_name' => 'ACME GmbH', ... ]);
```
If you only ever set `label`, set `company_name` instead (business) or
`salutation` + `person_name` (private person). There is no field to write
`display_name` into - it is composed from those.

## [0.7.0] - 2026-08-09

### Removed (BREAKING)
- **`first_name` and `last_name` are gone from `Customer`, replaced by
  `person_name`.** Splitting a private person's name into two fields bought
  nothing: what a document prints has always been ONE line, and many names do
  not divide cleanly ("Anna Maria von Berger"). A consumer customer now carries
  the whole name in `person_name`; `company_name` remains the printed recipient
  line and is composed from `salutation` + `person_name`.
  Migrate by sending `person_name` where you sent the two fields. Documents
  ISSUED before the change are untouched - their frozen recipient snapshot still
  carries the old pair and is merged on read, so an old invoice renders exactly
  as it did.

### Added
- `Customer.homepage`, `Customer.imprint_url` and `Customer.privacy_url` -
  informational addresses on the record. They never appear on a document; an
  organisation's own legal links live elsewhere. Stored without the scheme, so
  `https://example.at` comes back as `example.at`.

## [0.6.6] - 2026-08-04

### Changed
- **`remaining_amount` on `OutboundInvoice` is now 0 for a CANCELLED invoice.**
  It previously reported the unpaid balance of a document that had been reversed
  by a credit note, so a storno read as money still outstanding - to this client,
  to the portal and to the MCP tools alike. A cancelled invoice is not a
  receivable: whatever is still owed in either direction sits on the credit note
  that reversed it, never on the invoice it reversed.

  No money path changes. Every selector that MOVES money on the strength of this
  figure (dunning, SEPA credit transfers, direct debit) already excluded
  cancelled invoices, so this only makes the reported number agree with what
  those selectors were always doing.

  One behavioural consequence worth knowing: `PUT /outbound-invoices/{id}/set-paid`
  with `paid: true` on a CANCELLED invoice no longer records a payment, because
  there is no open amount left to settle. It still answers 200 with the invoice.

### Added
- **`settlement_status`** on `OutboundInvoice`: `draft` | `open` |
  `partially_paid` | `paid` | `cancelled`. The settlement state as one value, so
  it never has to be rebuilt out of `status` + `cancelled` + `paid`. That
  rebuilding is exactly what went wrong in practice - `paid: false` on a storno
  reads as an unpaid bill. On a credit note, `open` means a refund is still owed
  to the customer rather than money expected from them.
- **`cancelled`** on `OutboundInvoice`. The API has always returned it; it was
  simply never described, so there was no documented way to tell a reversed
  invoice from a live one.
- **The storno pair now names itself in both directions**, so relating the two
  documents needs no second request: `cancels_outbound_invoice_number` beside the
  existing `cancels_outbound_invoice_id` (on the credit note), and
  `cancelled_by_outbound_invoice_id` / `_number` (on the cancelled invoice).
- **`OutboundInvoice::isOutstanding()`, `isCancelled()` and `settlementStatus()`**
  on the model. `isOutstanding()` is the one to reach for: it answers "is money
  still expected" correctly for cancelled and draft documents, which `isPaid()`
  alone does not.

## [0.6.5] - 2026-08-02

### Added
- **`service_period_mode`** documented on `RecurringInvoice`. The field has always
  been accepted and returned by `/api/v1`; it was simply never described. It is
  documented now because it gained two values:
  - `ahead` - the term the invoice pays FOR, starting on its issue date
    (prepaid). A yearly schedule issued 2027-07-01 states
    `01.07.2027 - 30.06.2028`.
  - `behind` - the term that just ended, billed after the fact. The same
    schedule states `01.07.2026 - 30.06.2027`.

  Both print a date RANGE, unlike `current` and `previous`, which name a
  calendar period and print its name (`2027`, `2027/07`). A period ends the day
  before the next one starts, so consecutive invoices abut exactly.

### Note
Still undocumented on `RecurringInvoice`: roughly a dozen further top-level
fields the API returns. Several of them are pre-2026-07 legacy aliases
(`email_body_text`, `email_intro_text`, `footer_comment`, `header_info_override`,
`invoice_comment`) that should probably NOT become part of the published
contract. Closing that gap deliberately is its own piece of work.

## [0.6.4] - 2026-08-01

### Added
- **`payment_mode` and `payment_methods`** on `Customer`, `OutboundInvoice` and
  `RecurringInvoice`, readable and writable. They replace a single preferred
  method with two levels: `payment_mode` says WHO moves the money
  (`direct_debit` = we collect under a SEPA mandate, `all` = the customer pays by
  the organisation's standard setting, `individual` = they pay by the ways in
  `payment_methods`), and `payment_methods` is that selection.
- Direct debit is deliberately NOT one of `payment_methods`. It is the level
  above them, because it is the only method where the creditor pulls rather than
  the debtor pushing - a document offering both would invite a double payment.
  With `payment_mode: direct_debit` no way to pay is offered at all.
- `payment_methods` is kept when the mode is something else, so a record returns
  to those ways if a mandate is revoked. `online` is only ever offered when the
  organisation has a working Stripe configuration.

### Note
- The existing `payment_method` field is unchanged and still returned. Nothing
  that reads it breaks.

## [0.6.3] - 2026-07-31

### Fixed
- The contract no longer documents fields the API does not have. These were left
  behind by the 2026-07-03 structured-recipient and text renames, so a client
  writing to them got a silent no-op.
  - `Quote.footer_comment` is `pdf_footer`.
  - The `Customer` contact block is `recipient_email`, `recipient_email_name`,
    `cc_email`, `cc_email_name` and `contacts` - not `billing_email_address`,
    `contact_name`, `contact_email`, `contact_phone` or `recipient`.
  - Removed `customer_info`, `intro_text`, `billing_email_address`,
    `credit_note`, `due_date` and `invoice_comment` from the quote and outbound
    invoice INPUT schemas; the API rejects all six.

## [0.6.2] - 2026-07-31

### Removed
- **`document_term_id` is no longer returned** on quotes, outbound invoices or
  recurring invoices. It recorded which preset a document's terms came from, and
  nothing read it - while contradicting the rule beside it: terms are a snapshot,
  so a preset edit cannot change an issued document, yet the stored reference
  went on naming a preset after the text had been edited to say something else.

### Changed
- `document_term_id` remains valid **on input**, now documented as `writeOnly`:
  it LOADS a preset's text and day count onto the document, which then owns its
  own snapshot. A client that was reading the field back should read `terms_text`
  instead - that is, and always was, what renders.

  Requires portal build `1d2ff80` or later.

  > Released as a PATCH. Under this project's versioning rules MINOR and MAJOR
  > are decided by a human, not inferred from the shape of a change - so
  > "a removed response field is breaking, therefore minor" is not this
  > project's rule, however standard it is elsewhere.

## [0.6.1] - 2026-07-31

### Added
- `quote_valid_days` and `invoice_due_days` on `Customer` / `CustomerInput` -
  the third field of the Bedingungen block, which 0.6.0 shipped without. They
  override the preset's own day count for that customer.

  **Null means inherit, not 0.** 0 is a legitimate value ("expires the same
  day" / "due immediately"), so a null is the only way to say "no override".

### Changed
- Versioning now follows the portal's scheme (`tools/version.sh`,
  `tools/release.sh`): MAJOR by hand and only for a breaking contract change,
  MINOR by hand after substantial contract work and resetting PATCH, PATCH once
  per release that ships something. The source is the **git tag** - Composer
  derives a library's version from its tags, and a `version` key in
  composer.json would be a second answer that can disagree.

## [0.6.0] - 2026-07-31

### Added
- **Per-customer Bedingungen defaults**: `quote_document_term_id` /
  `quote_terms_text` and `invoice_document_term_id` / `invoice_terms_text` on
  `Customer` and `CustomerInput`. Assigning the customer to a quote or an
  outbound invoice applies them - the text AND the day count, so `valid_until` /
  `due_date` move with the terms rather than the text claiming 60 days while the
  date still says 14.

  Precedence, highest first: what the request explicitly sent, then the
  customer's default, then the organisation's default preset. A **default, not a
  snapshot** - the document takes its own copy, so editing a customer never
  rewrites a document that already exists.

  The preset must belong to the same organisation and match the document kind;
  anything else is refused rather than silently attached.

  Requires portal build `572ca7e` or later.

## [0.5.0] - 2026-07-30

### Added
- **Billing modality on positions**: `billing_mode` (`one_time` | `recurring`)
  and `recurring_interval` (`monthly` | `quarterly` | `yearly`). A quote can now
  offer one-time and recurring work together; its summary is split per modality
  instead of printing one combined total that adds a one-off charge to a monthly
  one. Defaults to `one_time`, so omitting both reproduces the previous
  behaviour exactly.
- The same two fields on **`Article`**, as the default a quote line inherits
  when it is created from the catalog.
- **Per-position `line_net`, `line_tax`, `line_gross`** (read-only). Do NOT
  re-derive line money as `quantity * price`: it is wrong for any line carrying a
  discount, and the tax applies to the discounted net. Emitting these removes the
  need for a second copy of that math in every client.
- `due_days` on the outbound-invoice payload - accepted on write and driving
  `due_date` all along, but never returned, so an editor had to back-compute the
  payment term.

### Changed
- **`POST /quotes/{id}/convert` was documented wrongly.** It returns **201**
  with **`outbound_invoice_id`**, not 200 with `invoice_id` - the key has not
  existed since the Invoice -> OutboundInvoice rename. Corrected.
- The same action now answers **409 `quote_is_recurring_only`** for a quote whose
  lines are all recurring: there is no one-time invoice to create, and silently
  billing a subscription once would be worse than refusing.

### Removed
- **`structured_totals`** on quotes and outbound invoices. It let a document
  print only its total instead of Netto / Steuer / Gesamt, but the portal had
  already dropped the control while the API key stayed - so the three surfaces
  disagreed about whether the feature existed. An invoice carrying VAT has to
  state its net and its tax; the genuine exception, a VAT-exempt issuer, is
  `small_business` and is unchanged. **Sending it now does nothing.**

  Requires portal build `13e0da8` or later.

## [Unreleased]

## [0.4.0] - 2026-07-29

### Added
- `small_business` + `vat_exemption_note` on quotes and outbound invoices.
  A Kleinunternehmer document legally shows a single total and a statutory note
  instead of a net/VAT breakdown; until now the wire carried neither, so a
  consumer rendering its own document saw only `tax_total: 0` and could not tell
  an exempt document from an ordinary 0 % one - and would render a legally wrong
  document. The note is emitted alongside the flag because the citation differs
  per issuer country (AT, DE, CH, neutral fallback) and follows the
  organisation's language; deriving it client-side means reimplementing tax law
  per market. Both read-only; null note when not exempt.

  Requires portal build `17c6cf5` or later.

## [0.3.0] - 2026-07-29

### Added
- `paid_at` on the outbound-invoice payload (`openapi.yaml`, `AGENTS.md`,
  `Model\OutboundInvoice` docblock). Derived settlement date: null until the
  invoice is fully paid, then the settling payment's own date (so a back-dated
  payment records a truthful date), cleared again if it falls back to partially
  paid. Read-only; no client method changes.
- `unit` + `unit_plural` on every position, and `unit` on articles. Send `unit`
  and leave the plural empty - the server resolves it from the organisation's
  unit vocabulary and snapshots it onto the line, so renaming or deleting a unit
  never re-inflects an issued document. An empty plural means the unit does not
  inflect (`8 h`).
- `terms_text` + `document_term_id` on quotes, outbound invoices and recurring
  invoices. The effective payment/validity terms, snapshotted from the
  organisation's default terms preset at creation; the id is provenance only.
  Omit `valid_until`/`due_date` on create to let the preset supply the window.
- `deposit_type`, `deposit_value`, `deposit_amount` and `remaining_amount` on
  quotes (down payment).

### Note

These fields were already being returned by the API - this release documents
them. Purely additive: no request or response shape changed, and no client
method signature changed.

## [0.2.0] - 2026-07-25

### Changed
- **Breaking:** `tax_multiplier` is now `tax_percent` on positions and articles,
  matching a hard rename on the server (no dual-key period). The meaning is
  unchanged - it always was a percentage (`20` = 20 %), which is exactly why the
  old name was wrong. Stored positions on pre-existing documents keep the old
  key internally; the server maps it on read, so historic VAT is unaffected.

## [0.1.0] - 2026-07-24

Initial release.

### Added
- `Client` with resources for the six v1 entities: customers, article
  categories, articles, quotes, outbound invoices, recurring invoices, plus
  user-token `auth` (register / login / me).
- CRUD (`list`, `autoPaging`, `get`, `create`, `update`, `delete`) and lifecycle
  actions (quote `issue`/`send`/`convert`/`pdf`; invoice
  `issue`/`send`/`cancel`/`setPaid`/`pdf`).
- Typed exception hierarchy keyed on the API's stable `error.key`
  (`AuthenticationException`, `PermissionException`, `NotFoundException`,
  `ConflictException`, `ValidationException`, `RateLimitException`,
  `ServerException`, `TransportException`).
- `Collection` pagination wrapper and lazy `autoPaging()`.
- PSR-18 / PSR-17 transport with auto-discovery via `php-http/discovery`.
- Canonical `openapi.yaml` contract, `README.md`, and `AGENTS.md` agent guide.

[Unreleased]: https://github.com/petarbeck/beckonbilling-api-client/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/petarbeck/beckonbilling-api-client/releases/tag/v0.1.0
