# 0002 - A hand-written thin client, not a generated one

**Status:** Accepted

**Date:** 2026-07-24 (commit `6d75cbe`, v0.1.0)

## Context

The contract is an OpenAPI document ([0001](0001-openapi-yaml-is-the-canonical-contract.md)),
so generating this package from it is the obvious option, and it was not
taken. The initial commit `6d75cbe` shipped `src/Client.php`,
`src/Configuration.php`, `src/Http/Transport.php`,
`src/Resource/AbstractResource.php`, `src/Model/Entity.php` and nine
hand-written exception classes under `src/Exception/`, with 37 mocked unit tests and no
generator step anywhere in the build.

The reason is what the package is for. A generated client mirrors the
schema: one class per entity, one typed property per documented field, and a
regeneration for every additive server change. This API adds fields
continuously - a consumer that has to wait for a client release before it can
read a new field is worse off than one reading an array. What is worth
typing here is not the payload; it is the small set of things a caller gets
wrong: transport, pagination, organisation scoping, and the failure modes.

## Decision

The client is written and maintained by hand, and stays thin.

- Uniform CRUD lives once, in `Resource\AbstractResource`
  (`list`, `autoPaging`, `get`, `create`, `update`, `delete`). A resource
  class adds only what is specific to it - `Resource\Quotes::issue()`,
  `Resource\OutboundInvoices::cancel()`, `Resource\Articles` and its variant
  sub-collection.
- Read-only collections extend `Resource\ReadOnlyResource`, whose
  `create`/`update`/`delete` throw a local `\LogicException` instead of
  sending a request the API answers 405.
- Payloads are wrapped by one model base, `Model\Entity`. It is **immutable**
  (`Model\Entity::offsetSet()` and `::offsetUnset()` throw
  `\LogicException`), exposes the payload through `__get`, `ArrayAccess`,
  `get()`, `has()`, `toArray()` and `JsonSerializable`, and passes through
  keys it has never heard of. Per-entity subclasses add `@property-read`
  hints and a few typed helpers (`Quote::isDraft()`,
  `OutboundInvoice::isPaid()`, `Order::isOpen()`).
- Transport is PSR-18/PSR-17, auto-discovered via `php-http/discovery`, with
  no HTTP client required as a hard dependency.

## Consequences

- A field the API adds is readable the day it ships, with no client release.
  A field the API *removes* or renames is silent in the other direction -
  the model happily returns `null` - which is one of the reasons the wire
  keeps both spellings during a rename window (see
  [0008](0008-document-uuids-replace-document-ids.md)).
- Static analysis of payload access is limited to what the `@property-read`
  hints cover. Where a targeted deprecation matters, it needs a real method:
  that is exactly why `Model\RecurringInvoice::documentIds()` exists as an
  accessor rather than as a hint (0008).
- The contract file must stay generator-friendly for the callers who *do*
  generate from it, even though this package never does (0001).
- Every contract change costs hand work here. That is the price paid for the
  two properties above, and it is what makes the sync rule in 0001 a
  discipline rather than a build step.

## References

`src/Client.php`, `src/Resource/AbstractResource.php`,
`src/Resource/ReadOnlyResource.php`, `src/Model/Entity.php`,
`src/Http/Transport.php`; `composer.json` (`require`, `suggest`);
`AGENTS.md` ("What this package is", "Models"); commit `6d75cbe`.
