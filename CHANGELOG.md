# Changelog

All notable changes to this project are documented here. This project adheres
to [Semantic Versioning](https://semver.org/).

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
