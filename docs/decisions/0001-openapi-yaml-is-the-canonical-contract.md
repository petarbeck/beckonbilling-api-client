# 0001 — `openapi.yaml` in this repository is the canonical `/api/v1` contract

**Status:** Accepted

**Date:** 2026-07-25

## Context

The contract for a public API usually lives with the server that implements
it, and this one did. On 2026-07-25 it moved: the server-side API document
and its Postman collection were withdrawn, the routes that served them were
removed, and this repository's `openapi.yaml` became the single description
of `/api/v1`. The file says so itself — `info.description`, second paragraph:
"This document is the **canonical contract** for the API. It is consumed by
the official PHP client (`beckonbilling/api-client`) and can be imported
directly into Postman, Insomnia, or an OpenAPI code generator." `AGENTS.md`
defers to it in its opening lines and calls itself "the client-usage
companion".

The consequence is the part worth recording: the server implements the
contract but no longer owns it. Every change to a `/api/v1` entity is now a
change in two repositories, and the one that has to be pulled is the public
one.

## Decision

`openapi.yaml` is the contract. Everything else in this repository is
derived from it or consumes it:

- `AGENTS.md` and `llms.txt` are companions written for agents; `README.md`
  is the human entry point. None of the three may state something
  `openapi.yaml` does not.
- The PHP code under `src/` is **one** consumer of the contract, not its
  definition. A generator, Postman or Insomnia are equally legitimate
  consumers, which is why the file has to stay strictly parseable — a
  duplicated mapping key made it unreadable to strict parsers and to several
  generators once, and was fixed as a defect (`CHANGELOG.md`, `[0.11.0]`).
- A server-side change to a v1 entity is not finished until this file
  describes it.

## Consequences

- A sentence here that contradicts the server is a **contract defect**, not
  a documentation nit. Two of them are recorded as such:
  [0006](0006-orders-are-in-the-contract-invoicing-is-not.md) (three
  sentences that outlived the feature they denied) and
  [0007](0007-readonly-only-where-the-server-refuses-the-key.md) (a
  `readOnly` flag on a field the server writes).
- Drift is the standing risk of the arrangement, and nothing automates the
  check: the two repositories are pulled by hand, in the same round of work.
- Because the contract is published, a change to it is visible to consumers
  who are not part of the projects that build the API — which is why the
  changelog is written as an announcement
  ([0004](0004-changelog-is-the-announcement-channel.md)).

## References

`openapi.yaml` (`info.description`); `AGENTS.md` (intro, "What this package
is"); `CHANGELOG.md` (`[0.11.0]`); vorgang
`2026-07-25-besitz-des-api-v1-vertrags`.
