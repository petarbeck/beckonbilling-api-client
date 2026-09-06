# 0007 — `readOnly` is claimed only where the server really refuses the key

**Status:** Accepted

**Date:** 2026-09-06 (commit `0148b9c`)

## Context

`RecurringInvoice.document_ids` was published as `readOnly: true` with the
words "not settable through this contract". The server reads that key on
both `POST` and `PUT` and stores the list. The flag was simply wrong, and it
was wrong in the expensive direction: a caller who believed the contract had
no documented way to set attachments at all, while a caller who ignored it
performed a write the contract said was impossible. A generator reading the
file produced an input type without the field.

The flag also hid something that mattered more than itself. An attachment id
that belongs to another organisation, or to nothing at all, is **dropped in
silence** rather than refused, so a wrong value cannot be told apart from a
saved one. A `readOnly` flag has no way to express that, and its presence
suggested the question did not arise.

## Decision

In `openapi.yaml`, `readOnly: true` is a statement about what the server
does, checked against the implementation, and nothing else.

- A field the server accepts on write is described as writable, whatever the
  intended usage is. Advice about which key to prefer belongs in the
  description, not in a flag that changes the generated type.
- An input schema lists **every** key the server reads.
  `RecurringInvoiceInput` now carries both `document_ids` and
  `document_uuids`.
- Where a wrong value is accepted and discarded silently, the description
  says so in words, because a schema cannot express it. That sentence is
  what turned this defect into the deprecation recorded in
  [0008](0008-document-uuids-replace-document-ids.md).

## Consequences

- Generated clients get input types that match the server, and the PHP
  client's own documentation stops contradicting its behaviour.
- Every `readOnly` in the file is now a claim that has to be verifiable
  against the implementation, and re-verifying them is manual work nobody
  automates ([0001](0001-openapi-yaml-is-the-canonical-contract.md)).
- Silent discard remains the API's default for an unrecognised body key.
  The only mechanism against it is opt-in `?strict=1`, which answers 400
  `unrecognised_keys` — and which **does nothing on `/articles` and
  `/article-categories`**, because those declare no key list. A pass there
  is not a validated request.
- Documented-but-silently-dropped values are a class of defect, not a
  one-off; the recurring invoice's integer attachment ids were the instance
  that got caught.

## References

`openapi.yaml` (`RecurringInvoice.document_ids`, `RecurringInvoiceInput`,
`info.description` "Strict mode"); `CHANGELOG.md` (`[Unreleased]`, "Fixed
(documentation only - no server change)"); `AGENTS.md` ("Strict mode",
"Field conventions"); commit `0148b9c`; vorgang
`2026-08-10-recurring-document-ids-integer`.
