# 0005 - Errors are typed classes, and callers branch on `error.key`

**Status:** Accepted

**Date:** 2026-07-24 (commit `6d75cbe`, v0.1.0)

## Context

The API answers a failure with `{ "error": { code, message, key } }`
(`openapi.yaml`, "Conventions"). Of the three fields only `key` is a stable
contract: `message` is prose meant for a human and can be reworded or
translated, and `code` is an internal number. The HTTP status alone is too
coarse to branch on - a 409 from this API is a draft PDF download, the
deletion of an issued invoice, an un-pay while linked payments exist, and
four distinct quote locks (`quote_issued_locked`, `quote_closed_locked`,
`quote_closed_undeletable`, `quote_positions_locked_by_billing`), all of
which a caller has to handle differently.

A caller that matches on the message string breaks the first time somebody
edits it - which is the same silent-breakage failure mode the rest of this
contract is written to avoid.

## Decision

Every non-2xx answer becomes a typed exception, and the documented way to
branch is `getErrorKey()`.

- `Exception\ApiException::fromResponse()` maps the status onto the most
  specific subclass: `AuthenticationException` (401),
  `PermissionException` (403), `NotFoundException` (404),
  `ConflictException` (409), `GoneException` (410),
  `ValidationException` (400/422), `RateLimitException` (429),
  `ServerException` (5xx), with `ApiException` as the base. A network
  failure becomes `TransportException` and keeps the original PSR-18 error
  as `getPrevious()`.
- Each carries `getStatusCode()`, `getErrorKey()`, `getApiErrorCode()` and
  `getResponse()`, so a caller can branch coarsely (`catch` a subclass) or
  precisely (`match ($e->getErrorKey())`) without parsing anything.
- A refusal the client can determine **without a request** is thrown in the
  same shape rather than sent: `Resource\Quotes::convert()` throws
  `GoneException` with `quote_conversion_moved` locally, and
  `Resource\ReadOnlyResource` throws a plain `\LogicException` for
  `create`/`update`/`delete` on `units` and `document-templates` - a
  programming error, not an API answer, and deliberately not an
  `ApiException`.

## Consequences

- A new error key needs no client release: the branch point is a string the
  model layer passes through, exactly as payload fields are
  ([0002](0002-hand-written-thin-client-no-generator.md)). A new *status
  class* does need one, and until then it surfaces as the base
  `ApiException`.
- The catalogue of keys lives in the contract, not in this package: the
  client never validates that a key it received is one the contract lists,
  so a key the server invents is delivered verbatim.
- Documentation everywhere states the key, not the message - `AGENTS.md`
  ("Errors"), `README.md` ("Error handling") and `openapi.yaml` all name
  keys, and the example code matches on them.
- Two capability refusals (`send_not_permitted`, `bank_not_permitted`) are
  ordinary 403 keys and not a separate exception class, so a caller that
  wants to tell "no permission" from "no capability" must read the key.

## References

`src/Exception/ApiException.php` (`ApiException::fromResponse()`,
`::getErrorKey()`), `src/Exception/*`, `src/Resource/ReadOnlyResource.php`,
`src/Resource/Quotes.php` (`Quotes::convert()`); `openapi.yaml`
("Conventions"); `AGENTS.md` ("Errors", "Capabilities"); commit `6d75cbe`.
