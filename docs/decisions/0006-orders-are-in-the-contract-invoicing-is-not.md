# 0006 - The order is part of this contract; invoicing one is not

**Status:** Accepted

**Date:** 2026-09-01 (v0.15.0, commit `aabfc88`); boundary restated
2026-09-06 (commit `0148b9c`)

## Context

On 2026-08-28 `POST /quotes/{id}/convert` was retired to 410
`quote_conversion_moved`, with no deprecation window - the same day the
portal's own equivalent action was closed. A won quote is no longer turned
into an invoice directly: it becomes an **order** (Auftrag) first, and the
order is what gets invoiced. That left this client with a gap no caller
could close on their own: it could see that a quote had become an order and
could do nothing with the order.

v0.15.0 (2026-09-01, commit `aabfc88`) closed the readable half of the gap.
Three sentences written before it survived it and then said the opposite of
the same documents' own `paths`: `openapi.yaml` `info.description` ("This
API has no order endpoint yet ... until an order route ships"), the
description of `Quote.order` ("The order itself is not reachable through
this API yet"), and the `status: 'won'` passage in `AGENTS.md`. Alongside
them, a gotcha in `AGENTS.md` still listed `orders` among the
portal-internal entities, and `llms.txt` counted eight entities where there
are nine. They were true when written, and had been false for five days.

## Decision

The order is a first-class entity of `/api/v1`, and the boundary that
remains is stated identically everywhere it is mentioned.

- `GET|POST /orders` and `GET|PUT|DELETE /orders/{id}` (tag *Orders*) are
  part of the contract. In this package: `Resource\Orders`, `Model\Order`
  with `Order::isOpen()` and `Order::isSettled()`, reachable as
  `$client->orders`, gated by the `orders` feature. `Quote::$order` carries
  the order's uuid as the handle.
- **An order has no sub-routes.** `/orders/{id}/<anything>` answers 404.
  Invoice-from-order and recurring-from-order create documents and stay out
  of this API deliberately; this ADR does not promise a replacement for the
  retired conversion call, and a consumer that ran quote to invoice through
  this client still has no single call for that step.
- Every document that states the boundary states this one: the order **is**
  readable and writable, **invoicing** it is not. `openapi.yaml`
  (`info.description` and `Quote.order`), `AGENTS.md` ("Orders", "Quote
  actions", "Gotchas") and `llms.txt` were pulled into line in `0148b9c`.

## Consequences

- The entity count is nine - seven writable (customers, article categories,
  articles, quotes, orders, outbound invoices, recurring invoices) and two
  read-only (units, document templates). A count stated in prose is a
  maintenance hazard and had already gone wrong twice; it is now stated in
  the same three places and has to move together with them.
- `Resource\Quotes::convert()` is kept as a method that throws
  `GoneException` locally rather than removed, so an upgrading caller gets a
  named refusal instead of a fatal "undefined method"
  ([0005](0005-errors-are-typed-and-branch-on-error-key.md)).
- The correction shipped as a changelog entry under "Fixed (documentation
  only - no server change)" - nothing on the wire moved
  ([0004](0004-changelog-is-the-announcement-channel.md)).
- A contract sentence that denies a feature is a claim with a shelf life.
  Both defects found in this round were of that kind (see also
  [0007](0007-readonly-only-where-the-server-refuses-the-key.md)), which is
  the practical content of the ownership rule in
  [0001](0001-openapi-yaml-is-the-canonical-contract.md).

## References

`CHANGELOG.md` (`[0.15.0]`, `[0.14.0]`, `[Unreleased]` "Fixed");
`openapi.yaml` (`info.description`, `Quote.order`, tag *Orders*);
`src/Resource/Orders.php`, `src/Model/Order.php`, `src/Resource/Quotes.php`
(`Quotes::convert()`); `AGENTS.md` ("Orders", "Gotchas"); `llms.txt`;
commits `aabfc88`, `0148b9c`; vorgang `2026-08-30-orders-im-api-client`.
