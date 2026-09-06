# 0008 - `document_uuids` replaces `document_ids`; the window closes at the next major

**Status:** Accepted

**Date:** 2026-09-06 (commits `0148b9c`, `33d222d`)

## Context

`/api/v1` has one convention it breaks exactly once. Its `info.description`
promises that "IDs are opaque UUID strings; integer ids are never exposed" -
and a recurring invoice carries its attached documents as a list of raw
internal integers, `document_ids`. Neither side of the contract could fix
that alone: the server cannot stop emitting a key that is in the published
contract and that every external caller reads, and this repository cannot
change what the server sends. It needed one round in which both moved.

The integer form is not merely inelegant. It cannot be checked: an id
belonging to another organisation, or to nothing at all, is dropped in
silence rather than refused
([0007](0007-readonly-only-where-the-server-refuses-the-key.md)).

The server has emitted and accepted a uuid-addressed `document_uuids` since
2026-08-11; the contract had never said so. Commit `0148b9c` documented it,
purely additively, and left open on purpose which of the two keys would
stay. Commit `33d222d` settled that question.

## Decision

**`document_uuids` is the way to read and write a recurring invoice's
attachments. `document_ids` is deprecated and is removed in the next major
release.**

- In the contract: `deprecated: true` on `RecurringInvoice.document_ids` and
  `RecurringInvoiceInput.document_ids`, a *Deprecations* section in
  `info.description`, and the "IDs are opaque UUID strings" convention bullet
  now points at it.
- **Both keys keep working, in both directions, for the whole window.**
  Nothing on the wire changed in either commit: no caller breaks in this
  release and none has to move on a particular day. A body carrying **both**
  is applied from `document_uuids`; `document_ids` is then not looked at
  (server-side `RecurringInvoiceEndpoint::apply()`).
- **No version number is written for either end of the window.** It is
  deprecated "as of the next minor release" and removed "in the next major
  release", named by version and never by date. MINOR and MAJOR are a
  human's call at tag time ([0003](0003-the-git-tag-is-the-version.md)), so
  a number promised here in advance would be a guess.
- In this package: `Model\RecurringInvoice::documentUuids()` is the
  accessor, and `Model\RecurringInvoice::documentIds()` sits beside it
  marked `@deprecated`. Both are **real methods** rather than
  `@property-read` hints, because a hint cannot carry a targeted deprecation
  an IDE or a static analyser will point at
  ([0002](0002-hand-written-thin-client-no-generator.md)). The tag sits on
  the one method, not on the class - otherwise every use of the model is
  flagged as stale.

## Consequences

- The removal is a breaking contract change, and it is what earns the next
  MAJOR: it closes the last place this API exposed an internal integer id.
- Consumers learn the window from the version range they pinned and from
  `CHANGELOG.md`, which is the only channel they have
  ([0004](0004-changelog-is-the-announcement-channel.md)). Nobody is
  notified, and no date is promised.
- Until the removal, both spellings must stay documented, tested and
  described in `AGENTS.md`, `llms.txt` and `README.md` (which grew a
  *Deprecations* section for it) - the transition costs duplication in five
  documents, deliberately.
- The `[Unreleased]` "Added" entry from `0148b9c` said the question of which
  key stays was still open; it was corrected in the same release rather than
  removed, per 0004.
- Tests cover both directions: both lists survive a round trip, a missing
  key reads back as an empty list, and a write puts `document_uuids` on the
  wire unchanged.
- The cross-component record for the integer-to-uuid migration stays open
  until the removal actually ships.

## References

`openapi.yaml` (`info.description` "Conventions" and "Deprecations";
`RecurringInvoice`, `RecurringInvoiceInput`); `CHANGELOG.md` (`[Unreleased]`
"Added" and "Deprecated"); `src/Model/RecurringInvoice.php`
(`RecurringInvoice::documentUuids()`, `::documentIds()`); `AGENTS.md`
("Field conventions"); `README.md` ("Deprecations"); `llms.txt`; commits
`0148b9c`, `33d222d`; vorgang `2026-08-10-recurring-document-ids-integer`.
